<?php

namespace App\Services;

use App\Enums\BajaMotivo;
use App\Enums\ProductoEstado;
use App\Enums\ReclamoEstado;
use App\Enums\ReclamoResolucion;
use App\Models\Compra;
use App\Models\CompraReclamo;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * El UNICO escritor de compras_reclamos y el unico que pone o saca un equipo
 * del estado Reclamo (ProductoEstado::soloPorDocumento()).
 *
 * Un equipo que llego fallado se reclama al proveedor: queda En reclamo, fuera
 * de la venta, del catalogo y de la reparacion. Decisiones del usuario sobre el
 * cierre:
 *  - Reemplazo: el proveedor manda otro equipo, que entra en LA MISMA compra
 *    con el costo del fallado; el total de la compra no cambia.
 *  - Descuento: el proveedor descuenta; el total de la compra baja.
 *  - Aceptado: el negocio se queda el equipo (reparado o tal cual).
 * En los dos primeros el fallado VUELVE al proveedor: su costo y su linea de
 * compra pasan a 0 (no es una perdida) y queda dado de baja con motivo
 * Devolucion, que no se revierte.
 *
 * ORDEN DE BLOQUEO: compra → equipos por id → stock.
 *
 * Ningun metodo abre transaccion: la abre el componente.
 */
class ReclamoService
{
    public function __construct(
        private EstadoProductoService $estados,
        private CompraService $compras,
        private BajaService $bajas,
    ) {}

    public function abrir(Compra $compra, int $productoId, string $motivo, User $user): CompraReclamo
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw ValidationException::withMessages(['motivo' => 'Escribe qué tiene el equipo.']);
        }

        $compra = Compra::with('proveedor')->whereKey($compra->id)->lockForUpdate()->firstOrFail();
        $producto = Producto::with('compraDetalle')->find($productoId);

        if (!$producto || $producto->compraDetalle?->compra_id !== $compra->id) {
            throw ValidationException::withMessages(['motivo' => 'Ese equipo no es de esta compra.']);
        }

        // Inventario o Roto: los que estan en el local y no tienen un tramite
        // encima (venta, reserva, reparacion). cambiar() bloquea el equipo,
        // exige el estado y rechaza los dados de baja.
        $this->estados->cambiar(
            $producto->id,
            [ProductoEstado::Inventario, ProductoEstado::Roto],
            ProductoEstado::Reclamo,
            'En reclamo al proveedor ' . ($compra->proveedor?->nombre ?? '') . ": {$motivo}.",
            ['compra_id' => $compra->id],
        );

        return CompraReclamo::create([
            'compra_id' => $compra->id,
            'producto_id' => $producto->id,
            'motivo' => mb_substr($motivo, 0, 255),
            'estado' => ReclamoEstado::Abierto->value,
            'user_id' => $user->id,
        ]);
    }

    /**
     * @param  array  $datos  Reemplazo: imei, bateria_porcentaje, color (opcional).
     *                        Aceptado: destino (Inventario|Roto). nota (todos).
     */
    public function cerrar(CompraReclamo $reclamo, ReclamoResolucion $resolucion, array $datos, User $user): CompraReclamo
    {
        $compra = Compra::whereKey($reclamo->compra_id)->lockForUpdate()->firstOrFail();
        $reclamo = CompraReclamo::with('producto')->whereKey($reclamo->id)->lockForUpdate()->firstOrFail();

        if (!$reclamo->estaAbierto()) {
            throw ValidationException::withMessages(['resolucion' => 'Ese reclamo ya está cerrado. Recarga la pantalla.']);
        }

        $fallado = $reclamo->producto;
        $nota = trim((string) ($datos['nota'] ?? '')) ?: null;
        $reemplazo = null;

        switch ($resolucion) {
            case ReclamoResolucion::Reemplazo:
                $reemplazo = $this->agregarReemplazo($compra, $fallado, $datos, $reclamo);
                $this->devolver($compra, $fallado, "reemplazado por el IMEI {$reemplazo->imei}");
                break;

            case ReclamoResolucion::Descuento:
                $this->devolver($compra, $fallado, 'el proveedor descontó Bs ' . number_format((float) $fallado->costo_unidad, 2));
                break;

            case ReclamoResolucion::Aceptado:
                $destino = ProductoEstado::tryFrom((string) ($datos['destino'] ?? ''));

                if (!in_array($destino, [ProductoEstado::Inventario, ProductoEstado::Roto], true)) {
                    throw ValidationException::withMessages(['destino' => 'Elige si vuelve a Inventario o a Roto.']);
                }

                $this->estados->cambiar(
                    $fallado->id,
                    ProductoEstado::Reclamo,
                    $destino,
                    "Reclamo #{$reclamo->id} cerrado: se acepta el equipo" . ($nota ? ". {$nota}" : '.'),
                    ['compra_id' => $compra->id],
                );
                break;
        }

        $reclamo->update([
            'estado' => ReclamoEstado::Resuelto->value,
            'resolucion' => $resolucion->value,
            'producto_reemplazo_id' => $reemplazo?->id,
            'nota_cierre' => $nota ? mb_substr($nota, 0, 255) : null,
            'cerrado_por' => $user->id,
            'cerrado_at' => now(),
        ]);

        return $reclamo;
    }

    /** El equipo que manda el proveedor: los datos del fallado, con su IMEI y su costo. */
    private function agregarReemplazo(Compra $compra, Producto $fallado, array $datos, CompraReclamo $reclamo): Producto
    {
        $datos['imei'] = trim((string) ($datos['imei'] ?? ''));

        $v = Validator::make($datos, [
            'imei' => 'required|string|max:20|unique:productos,imei',
            'bateria_porcentaje' => 'required|integer|min:1|max:100',
            'color' => 'nullable|string',
        ], [
            'imei.required' => 'Escribe el IMEI del equipo de reemplazo.',
            'imei.unique' => 'Ese IMEI ya está registrado en el sistema.',
        ], ['bateria_porcentaje' => 'batería']);

        if ($v->fails()) {
            throw ValidationException::withMessages($v->errors()->messages());
        }

        $color = $datos['color'] ?: $fallado->color;

        return $this->compras->agregarProducto($compra, [
            'producto_modelo_id' => $fallado->producto_modelo_id,
            'almacenamiento' => $fallado->almacenamiento,
            'version' => $fallado->version,
            'color' => $color,
            'imei' => $datos['imei'],
            'bateria_porcentaje' => (int) $datos['bateria_porcentaje'],
            'estado_grado' => $fallado->estado_grado,
            'tipo_venta' => $fallado->tipo_venta,
            'costo_unidad' => (float) $fallado->costo_unidad,
            // El mismo costo y en la misma moneda que el fallado.
            'costo_moneda' => $fallado->costo_moneda,
            'costo_moneda_monto' => $fallado->costo_moneda_monto,
            'costo_tipo_cambio' => $fallado->costo_tipo_cambio,
            'precio_cliente' => (float) $fallado->precio_cliente,
            'precio_vendedor' => (float) $fallado->precio_vendedor,
            'disponible_catalogo' => (bool) $fallado->disponible_catalogo,
            'sucursal_id' => $fallado->sucursal_id,
            'descripcion' => trim(($fallado->modelo?->nombre ?? '') . " color {$color} de {$fallado->almacenamiento} con "
                . (int) $datos['bateria_porcentaje'] . "% de batería IMEI: {$datos['imei']}"),
        ]);
    }

    /**
     * El fallado vuelve al proveedor: su costo y su linea de compra a 0 (no es
     * una perdida) y dado de baja con motivo Devolucion.
     */
    private function devolver(Compra $compra, Producto $fallado, string $detalle): void
    {
        $producto = Producto::whereKey($fallado->id)->lockForUpdate()->firstOrFail();

        $producto->anotar('editado', "Devuelto al proveedor (compra #{$compra->id}): {$detalle}. Su costo pasa a 0.", ['compra_id' => $compra->id]);
        // En BOB: si quedara en USD, Producto volveria a calcular el costo
        // desde los dolares y el devuelto recuperaria su costo.
        $producto->update(['costo_unidad' => 0, 'costo_moneda' => \App\Enums\Moneda::BOB->value]);
        $producto->recalcularCosto();
        // La linea de compra y el total siguen al costo (con el control de que
        // el total no quede por debajo de lo ya pagado).
        $this->compras->actualizarCostoProducto($producto->fresh());

        $this->bajas->darDeBajaProducto($producto->id, BajaMotivo::Devolucion, "Devuelto al proveedor: {$detalle}.");
    }
}
