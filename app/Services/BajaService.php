<?php

namespace App\Services;

use App\Enums\ArticuloTipo;
use App\Enums\BajaMotivo;
use App\Enums\ProductoEstado;
use App\Models\Bitacora;
use App\Models\Producto;
use App\Models\StockBaja;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Dar de baja: un equipo (se archiva) o unidades de un repuesto/accesorio (se
 * retiran del stock con su motivo).
 *
 * Fuera (salio del local) y Roto (esta roto) NO son bajas: son estados. La baja
 * de un equipo es un atributo aparte que lo archiva sin tocar su estado: deja
 * de aparecer en listados, ventas y catalogo (Producto::scopeVigentes /
 * scopeDisponibles) y EstadoProductoService rechaza cualquier cambio sobre el.
 *
 * Ningun metodo abre transaccion: la abre el componente.
 */
class BajaService
{
    public function __construct(
        private StockService $stock,
    ) {}

    public function darDeBajaProducto(int $productoId, BajaMotivo $motivo, ?string $nota = null): Producto
    {
        $producto = Producto::whereKey($productoId)->lockForUpdate()->firstOrFail();

        if ($producto->estaDadoDeBaja()) {
            throw ValidationException::withMessages(['motivo' => "El equipo {$producto->imei} ya está dado de baja."]);
        }

        // Un equipo vendido o reservado tiene un cliente detras: primero se anula
        // la venta o se libera la reserva. Uno en reparacion esta en el banco de
        // un tecnico: terminarla lo devuelve a Inventario con
        // EstadoProductoService, que rechaza los dados de baja, y la pantalla
        // del tecnico quedaria sin poder cerrar su lote.
        if ($producto->estado === ProductoEstado::EnCompra->value) {
            throw ValidationException::withMessages([
                'motivo' => "El equipo {$producto->imei} es de una compra sin finalizar: quítalo de la compra en vez de darlo de baja.",
            ]);
        }

        if (in_array($producto->estado, [...ProductoEstado::vendidos(), ProductoEstado::Reserva->value, ProductoEstado::Reparacion->value], true)) {
            throw ValidationException::withMessages([
                'motivo' => "El equipo {$producto->imei} está en " . ProductoEstado::labelDe($producto->estado)
                    . ': anula la venta, libera la reserva o termina la reparación antes de darlo de baja.',
            ]);
        }

        // Un fallado en reclamo solo sale como Devolucion (al cerrar el reclamo,
        // ReclamoService), y la Devolucion solo existe para el.
        $enReclamo = $producto->estado === ProductoEstado::Reclamo->value;
        if ($enReclamo !== ($motivo === BajaMotivo::Devolucion)) {
            throw ValidationException::withMessages([
                'motivo' => $enReclamo
                    ? "El equipo {$producto->imei} está en reclamo al proveedor: cierra el reclamo desde la compra."
                    : 'La devolución al proveedor se registra al cerrar un reclamo, no como baja suelta.',
            ]);
        }

        $nota = trim((string) $nota) ?: null;

        // anotar() ANTES del save: el Observer escribe UNA fila con el evento,
        // la frase y el diff. Evento de BitacoraEvento ('baja'), no un estado:
        // la baja no mueve el estado del equipo.
        $producto->anotar('baja', 'Dado de baja: ' . $motivo->label() . ($nota ? ". {$nota}" : '.'));
        $producto->update([
            'dado_de_baja_at' => now(),
            'motivo_baja' => $motivo->value,
            'nota_baja' => $nota,
            'baja_user_id' => Auth::id(),
        ]);

        return $producto;
    }

    public function revertirBajaProducto(int $productoId): Producto
    {
        $producto = Producto::whereKey($productoId)->lockForUpdate()->firstOrFail();

        if (!$producto->estaDadoDeBaja()) {
            throw ValidationException::withMessages(['motivo' => "El equipo {$producto->imei} no está dado de baja."]);
        }

        // Devuelto al proveedor: ya no esta en el negocio y su costo quedo en 0.
        if ($producto->motivo_baja === BajaMotivo::Devolucion->value) {
            throw ValidationException::withMessages(['motivo' => "El equipo {$producto->imei} se devolvió al proveedor: esa baja no se revierte."]);
        }

        $producto->anotar('baja-revertida', 'Baja revertida: el equipo vuelve al inventario vigente.');
        $producto->update([
            'dado_de_baja_at' => null,
            'motivo_baja' => null,
            'nota_baja' => null,
            'baja_user_id' => null,
        ]);

        return $producto;
    }

    /**
     * Retira unidades del stock de una sucursal con su motivo. El costo se
     * congela para el reporte de perdidas.
     *
     * El stock se mueve con SQL crudo, invisible para BitacoraObserver: el
     * hecho se registra a mano en la bitacora del articulo.
     */
    public function darDeBajaStock(ArticuloTipo $tipo, int $id, int $sucursalId, int $cantidad, BajaMotivo $motivo, ?string $nota = null): StockBaja
    {
        if ($cantidad < 1) {
            throw ValidationException::withMessages(['cantidad' => 'Indica cuántas unidades dar de baja.']);
        }

        $articulo = $tipo->buscar($id) ?? throw ValidationException::withMessages(['detalles' => 'El artículo ya no existe.']);
        $nota = trim((string) $nota) ?: null;

        // El WHERE cantidad >= ? de retirar() valida que esas unidades existan.
        $this->stock->retirar($tipo, $id, $sucursalId, $cantidad);
        $this->stock->recalcularTotales();

        $baja = StockBaja::create([
            $tipo->columna() => $id,
            'sucursal_id' => $sucursalId,
            'cantidad' => $cantidad,
            'costo' => (float) $articulo->costo,
            'motivo' => $motivo->value,
            'nota' => $nota,
            'user_id' => Auth::id(),
        ]);

        Bitacora::registrar(
            $articulo,
            'stock-baja',
            "Baja de {$cantidad} unidad(es): " . $motivo->label() . ($nota ? ". {$nota}" : '.'),
            [$tipo->columna() => $id, 'sucursal_id' => $sucursalId],
        );

        return $baja;
    }
}
