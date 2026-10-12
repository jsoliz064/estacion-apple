<?php

namespace App\Services;

use App\Enums\ProductoEstado;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Validation\ValidationException;

/**
 * Anular una linea de venta, o la venta entera: el unico camino.
 *
 * Una linea puede ser:
 *   - un equipo: se descobran ANTES sus repuestos de taller (con la FK de cobro
 *     en RESTRICT es obligatorio), se borra la linea y el equipo vuelve a
 *     Inventario con su precondicion (EstadoProductoService);
 *   - un cobro de taller: se borra la linea; NO devuelve stock (la pieza sigue
 *     montada en el equipo);
 *   - un repuesto o accesorio: el stock vuelve a la sucursal DE LA LINEA (no a
 *     la de la venta ni a la del equipo), y se borra la linea.
 *
 * Los pagos: anular la venta entera (o su ultima linea) los borra, uno por uno
 * en la bitacora, porque se entiende que el dinero se devolvio. Anular una
 * linea suelta no puede dejar el total por debajo de lo cobrado
 * (Venta::recalcularTotales lo rechaza) y despues PagoService::sincronizar()
 * deja la venta pagada o a credito segun el saldo nuevo.
 *
 * Si la venta queda sin lineas, la cabecera se borra: una venta sin lineas es
 * basura que se lee como "no se guardo". Las lineas tienen la FK de venta en
 * RESTRICT a proposito: un camino que olvidara anularlas antes falla con un
 * error visible, en vez de llevarselas por cascada sin devolver el stock.
 *
 * TRANSACCIONES
 * No abre la suya: asume la del llamador. Media anulacion es peor que ninguna.
 */
class AnulacionVentaService
{
    public function __construct(
        private EstadoProductoService $estados,
        private RepuestosDeReparacionService $repuestos,
        private StockService $stock,
        private PagoService $pagos,
    ) {}

    /**
     * Quita una linea de su venta.
     *
     * @return bool true si la venta entera quedo vacia y se borro
     */
    public function anularLinea(VentaDetalle $linea): bool
    {
        // El regalo viaja con su equipo: se va cuando se anula el equipo.
        if ($linea->esRegalo()) {
            throw ValidationException::withMessages([
                'detalles' => 'El regalo se anula junto con su equipo.',
            ]);
        }

        $venta = $linea->venta;

        $this->deshacerLinea($venta, $linea);
        $this->stock->recalcularTotales();

        if ($venta->detalles()->doesntExist()) {
            $this->pagos->anularTodos($venta, "se anuló la última línea de la venta #{$venta->id}.");
            app(ComisionService::class)->desligarVenta($venta);
            $venta->delete();

            return true;
        }

        $venta->refresh();
        $venta->recalcularTotales();
        $this->pagos->sincronizar($venta);

        return false;
    }

    /** Anula todas las lineas y borra la venta. */
    public function anularVenta(Venta $venta): void
    {
        // Los cobros primero: con la FK en RESTRICT, ninguna linea de equipo se
        // puede tocar mientras su cobro exista.
        $this->repuestos->cancelarCobros($venta);

        // Sin los regalos: los borra deshacerLinea() con su equipo.
        $lineas = $venta->detalles()->whereNull('producto_regalo_id')->orderByRaw('producto_id IS NULL')->orderBy('id')->get();

        foreach ($lineas as $linea) {
            $this->deshacerLinea($venta, $linea);
        }

        $this->stock->recalcularTotales();
        $this->pagos->anularTodos($venta, "se anuló la venta #{$venta->id}.");
        app(ComisionService::class)->desligarVenta($venta);
        $venta->delete();
    }

    private function deshacerLinea(Venta $venta, VentaDetalle $linea): void
    {
        // El id se guarda antes: si la venta se borra, deja de servir.
        $ventaId = $venta->id;

        if ($linea->producto_id) {
            $this->repuestos->cancelarCobros($venta, $linea->producto_id);
            // Sus regalos se van con el (siguen asignados al equipo, sin stock que devolver).
            VentaDetalle::borrarRegalosDe($ventaId, $linea->producto_id);
            $linea->delete();

            // Sus accesorios pagados quedan en la venta, ya sin agrupar bajo el.
            VentaDetalle::where('venta_id', $ventaId)->where('producto_asociado_id', $linea->producto_id)
                ->update(['producto_asociado_id' => null]);

            // La precondicion importa: si el producto ya no esta vendido,
            // alguien lo movio y bajarlo a Inventario pisaria ese cambio.
            $this->estados->cambiar(
                $linea->producto_id,
                array_map(fn($v) => ProductoEstado::from($v), ProductoEstado::vendidos()),
                ProductoEstado::Inventario,
                "Producto devuelto al inventario: se anulo su linea de la venta #{$ventaId}.",
                ['venta_id' => $ventaId],
            );

            return;
        }

        if ($linea->esCobro()) {
            // Un cobro de taller: la pieza sigue montada, no hay stock que
            // devolver. cancelarCobros deja la nota en el historial del equipo.
            $this->repuestos->cancelarCobros($venta, null, $linea->id);

            return;
        }

        $tipo = $linea->tipoLinea()->articulo();
        $this->stock->ingresar($tipo, $linea->{$tipo->columna()}, $linea->sucursal_id, (int) $linea->cantidad);
        $linea->delete();
    }
}
