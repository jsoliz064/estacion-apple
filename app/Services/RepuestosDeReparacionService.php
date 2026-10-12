<?php

namespace App\Services;

use App\Enums\ReparacionTipo;
use App\Models\Bitacora;
use App\Models\ProductoReparacionRepuesto;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Support\Collection;

/**
 * Cobrar como parte de la venta los repuestos que se montaron en las
 * reparaciones de un telefono antes de venderlo.
 *
 * El caso: el cliente quiere el equipo con la bateria al 100%. El telefono pasa
 * a reparacion, se le monta una bateria, y esa pieza solo inflaria el costo del
 * telefono sin existir nunca como venta.
 *
 * Desde la venta unificada, el cobro es UNA LINEA MAS de la misma venta (antes
 * era una segunda venta de repuestos enlazada por venta_id). Dos reglas siguen
 * gobernando todo:
 *
 *  1. NO se toca el stock. La pieza salio del almacen cuando el tecnico la
 *     monto. Volver a descontar la restaria dos veces. La linea lo declara con
 *     `producto_reparacion_repuesto_id` (VentaDetalle::stockYaDescontado()).
 *
 *  2. La linea se escribe con COSTO CERO. Ese costo ya viaja dentro de
 *     `productos.costo_total`, congelado en el costo de la linea del equipo.
 *     El repuesto aporta ingreso puro: el cliente lo paga encima del telefono.
 *
 * Ningun metodo abre transaccion ni recalcula los totales de la venta: lo hace
 * el llamador (VentaService, AnulacionVentaService) una vez al final.
 */
class RepuestosDeReparacionService
{
    /**
     * Lineas de reparacion de un producto que todavia se pueden cobrar.
     *
     * Solo reparaciones Normal: en una Garantia la pieza se le puso gratis al
     * cliente, y un Trabajo Externo ya tiene su propio `cobro_cliente`. Cobrar
     * cualquiera de las dos seria cobrar dos veces.
     */
    public function elegibles(int $productoId): Collection
    {
        return ProductoReparacionRepuesto::query()
            ->with(['repuesto:id,nombre,fabricante,precio,sku', 'reparacion:id,producto_id,tipo,created_at'])
            ->whereHas('reparacion', fn($query) => $query
                ->where('producto_id', $productoId)
                ->where('tipo', ReparacionTipo::Normal->value))
            ->whereNotIn('id', $this->yaCobrados())
            ->orderBy('id')
            ->get();
    }

    /** Cuantas lineas cobrables tiene el producto. Para pintar el boton. */
    public function contarElegibles(int $productoId): int
    {
        return ProductoReparacionRepuesto::query()
            ->whereHas('reparacion', fn($query) => $query
                ->where('producto_id', $productoId)
                ->where('tipo', ReparacionTipo::Normal->value))
            ->whereNotIn('id', $this->yaCobrados())
            ->count();
    }

    /**
     * Ids de linea de reparacion que ya se cobraron.
     *
     * La columna es UNICA en la base (vd_reparacion_repuesto_unico), asi que
     * esto es comodidad para la pantalla: quien impide de verdad el doble cobro
     * es el indice.
     */
    private function yaCobrados()
    {
        return VentaDetalle::query()
            ->whereNotNull('producto_reparacion_repuesto_id')
            ->select('producto_reparacion_repuesto_id');
    }

    /**
     * Agrega a la venta las lineas de cobro. Se llama DENTRO de la transaccion
     * de la venta: si algo falla aqui, la venta entera revierte.
     *
     * @param  array  $lineas  cada una con producto_reparacion_repuesto_id y
     *                         precio (sueltas o agrupadas por producto)
     * @return int cuantas lineas se agregaron
     */
    public function registrar(Venta $venta, array $lineas): int
    {
        $lineas = $this->normalizar($lineas);

        if (empty($lineas)) {
            return 0;
        }

        // Se releen las lineas de reparacion de la base en vez de fiarse de lo
        // que llego de la pantalla: la cantidad y el repuesto salen de aqui, y
        // solo el precio viene del vendedor.
        $origenes = ProductoReparacionRepuesto::with('repuesto', 'reparacion.producto')
            ->whereIn('id', array_keys($lineas))
            ->get();

        foreach ($origenes as $origen) {
            $precio = $lineas[$origen->id];

            VentaDetalle::create([
                'venta_id' => $venta->id,
                'repuesto_id' => $origen->repuesto_id,
                'producto_reparacion_repuesto_id' => $origen->id,
                // La sucursal de la pieza, no la de la venta: es de donde salio
                // el stock, aunque este cobro no lo mueva.
                'sucursal_id' => $origen->sucursal_id ?? $venta->sucursal_id,
                'cantidad' => $origen->cantidad,
                // Costo cero a proposito: lo carga el telefono. Ver la cabecera.
                'costo' => 0,
                'subtotal_costo' => 0,
                'precio' => $precio,
                'descuento' => 0,
                'subtotal' => round($precio * $origen->cantidad, 2),
            ]);

            // La nota va sobre el TELEFONO: es su historial el que tiene que
            // contar que esa pieza se cobro con su venta. Evento 'cobro' y no
            // un estado: un hecho que no mueve el estado no se escribe con
            // nombre de estado.
            if ($producto = $origen->reparacion?->producto) {
                $nombre = $origen->repuesto?->nombre ?? 'Repuesto';

                Bitacora::registrar(
                    $producto,
                    'cobro',
                    "Repuesto {$nombre} cobrado con la venta #{$venta->id}. "
                        . "Se monto en la reparacion #{$origen->producto_reparacion_id} y su stock ya se descontó entonces.",
                    [
                        'producto_reparacion_id' => $origen->producto_reparacion_id,
                        'venta_id' => $venta->id,
                        'repuesto_id' => $origen->repuesto_id,
                    ],
                );
            }
        }

        return $origenes->count();
    }

    /**
     * Deshace el cobro de repuestos de una venta, o solo el de UN producto.
     *
     * NO devuelve stock: las piezas siguen montadas en el telefono, se anule la
     * venta o no. Al borrar las lineas, sus reparaciones vuelven a quedar
     * cobrables. Hay que llamarlo ANTES de borrar la linea del equipo: con la FK
     * de cobro en RESTRICT, la pieza de reparacion no se puede tocar mientras
     * su cobro exista.
     *
     * @param  int|null  $detalleId  solo esa linea de cobro (anular una linea suelta)
     * @return int cuantas lineas se descobraron
     */
    public function cancelarCobros(Venta $venta, ?int $productoId = null, ?int $detalleId = null): int
    {
        $cobros = VentaDetalle::with('repuesto', 'reparacionRepuesto.reparacion.producto')
            ->where('venta_id', $venta->id)
            ->whereNotNull('producto_reparacion_repuesto_id')
            ->when($detalleId, fn($q) => $q->whereKey($detalleId))
            ->get()
            // Cancelar UN producto de una venta con varios solo descobra lo
            // suyo; lo del resto sigue cobrado.
            ->filter(fn($d) => $productoId === null
                || $d->reparacionRepuesto?->reparacion?->producto_id == $productoId);

        // La bitacora no borra: el cobro existio y se deshizo, y las dos cosas
        // quedan contadas.
        foreach ($cobros as $detalle) {
            if ($producto = $detalle->reparacionRepuesto?->reparacion?->producto) {
                Bitacora::registrar(
                    $producto,
                    'cobro-anulado',
                    "Cobro del repuesto {$detalle->repuesto?->nombre} anulado: la venta #{$venta->id} ya no lo incluye. "
                        . 'La pieza sigue montada en el equipo.',
                    [
                        'venta_id' => $venta->id,
                        'repuesto_id' => $detalle->repuesto_id,
                        'producto_reparacion_id' => $detalle->reparacionRepuesto?->producto_reparacion_id,
                    ],
                );
            }
        }

        if ($cobros->isNotEmpty()) {
            VentaDetalle::whereKey($cobros->pluck('id'))->delete();
        }

        return $cobros->count();
    }

    /**
     * En que venta se cobro una pieza de reparacion, o null. Para el aviso de
     * ReparacionEditModal antes de quitar o cambiar una pieza ya cobrada.
     */
    public function ventaDelCobro(int $productoReparacionRepuestoId): ?int
    {
        return VentaDetalle::where('producto_reparacion_repuesto_id', $productoReparacionRepuestoId)->value('venta_id');
    }

    /**
     * Aplana lo que llega de las pantallas a [linea_reparacion_id => precio].
     * Se descartan las lineas sin precio positivo: un cobro de cero no es un
     * cobro.
     */
    /**
     * Lo que sumaran a la venta estas lineas de cobro, con la cantidad leida de
     * la base (como registrar()). VentaService lo necesita ANTES de vender los
     * equipos, para saber si la venta queda a credito.
     */
    public function totalPrevisto(array $lineas): float
    {
        $lineas = $this->normalizar($lineas);

        if (empty($lineas)) {
            return 0.0;
        }

        $cantidades = ProductoReparacionRepuesto::whereIn('id', array_keys($lineas))->pluck('cantidad', 'id');
        $total = 0.0;

        foreach ($cantidades as $id => $cantidad) {
            $total += round($lineas[$id] * (int) $cantidad, 2);
        }

        return round($total, 2);
    }

    private function normalizar(array $lineas): array
    {
        $plano = [];

        foreach ($lineas as $entrada) {
            // Una lista de lineas, o un grupo de listas por producto.
            $grupo = isset($entrada['producto_reparacion_repuesto_id']) ? [$entrada] : $entrada;

            foreach ((array) $grupo as $linea) {
                $id = (int) ($linea['producto_reparacion_repuesto_id'] ?? 0);
                $precio = round((float) ($linea['precio'] ?? 0), 2);

                if ($id > 0 && $precio > 0) {
                    $plano[$id] = $precio;
                }
            }
        }

        return $plano;
    }
}
