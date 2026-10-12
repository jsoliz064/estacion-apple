<?php

namespace App\Services;

use App\Enums\ArticuloTipo;
use App\Enums\ProductoEstado;
use App\Models\Accesorio;
use App\Models\Producto;
use App\Models\ProductoRegalo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Accesorios de regalo de un equipo (reemplazan al viejo "costo de envio").
 * Tres cosas pasan a la vez (docs/02): baja el stock del accesorio, sube el
 * costo del equipo (productos.costo_regalos) y queda el detalle a la vista.
 *
 * Solo mientras el equipo no esta vendido ni dado de baja: al vender, el costo
 * de la linea de venta congela el costo_total que ya incluye los regalos, y
 * cambiarlo despues reescribiria la ganancia de una venta cerrada.
 *
 * El costo y la sucursal de origen se congelan en la fila: quitar el regalo
 * devuelve el stock ahi.
 *
 * Ningun metodo abre transaccion: la abre el componente.
 */
class ProductoRegalosService
{
    public function __construct(
        private StockService $stock,
    ) {}

    /** Agrega un regalo, o suma unidades si ese accesorio ya estaba. */
    public function agregar(int $productoId, int $accesorioId, int $cantidad, int $sucursalId): ProductoRegalo
    {
        $producto = $this->bloquearEditable($productoId);
        $accesorio = Accesorio::findOrFail($accesorioId);

        if ($cantidad < 1) {
            throw ValidationException::withMessages(['cantidad' => 'Indica cuántas unidades regalar.']);
        }

        $regalo = ProductoRegalo::where('producto_id', $producto->id)->where('accesorio_id', $accesorio->id)->first();

        if ($regalo) {
            return $this->ajustar($regalo->id, $regalo->cantidad + $cantidad);
        }

        // El WHERE cantidad >= ? de retirar() valida el stock.
        $this->stock->retirar(ArticuloTipo::Accesorio, $accesorio->id, $sucursalId, $cantidad);
        $this->stock->recalcularTotales();

        $regalo = ProductoRegalo::create([
            'producto_id' => $producto->id,
            'accesorio_id' => $accesorio->id,
            'sucursal_id' => $sucursalId,
            'cantidad' => $cantidad,
            'costo' => (float) $accesorio->costo,
            'subtotal_costo' => round((float) $accesorio->costo * $cantidad, 2),
            'user_id' => Auth::id(),
        ]);

        $this->recalcular($producto, 'regalo', "Regalo agregado: {$cantidad} × {$accesorio->nombre}.", $accesorio->id);

        return $regalo;
    }

    /** Cambia la cantidad de un regalo (con el costo que se congelo al agregarlo). */
    public function ajustar(int $regaloId, int $cantidad): ProductoRegalo
    {
        $regalo = ProductoRegalo::with('accesorio')->findOrFail($regaloId);
        $producto = $this->bloquearEditable($regalo->producto_id);

        if ($cantidad < 1) {
            $this->quitar($regaloId);

            return $regalo;
        }

        $this->stock->ajustarSalida(ArticuloTipo::Accesorio, $regalo->accesorio_id, $regalo->sucursal_id, (int) $regalo->cantidad, $cantidad);
        $this->stock->recalcularTotales();

        $antes = $regalo->cantidad;
        $regalo->update([
            'cantidad' => $cantidad,
            'subtotal_costo' => round((float) $regalo->costo * $cantidad, 2),
        ]);

        $this->recalcular($producto, 'regalo', "Regalo {$regalo->accesorio?->nombre}: {$antes} → {$cantidad} unidad(es).", $regalo->accesorio_id);

        return $regalo;
    }

    public function quitar(int $regaloId): void
    {
        $regalo = ProductoRegalo::with('accesorio')->findOrFail($regaloId);
        $producto = $this->bloquearEditable($regalo->producto_id);

        // Vuelve a la sucursal de donde salio, no a la que tenga el equipo ahora.
        $this->stock->ingresar(ArticuloTipo::Accesorio, $regalo->accesorio_id, $regalo->sucursal_id, (int) $regalo->cantidad);
        $this->stock->recalcularTotales();

        $nombre = $regalo->accesorio?->nombre;
        $accesorioId = $regalo->accesorio_id;
        $regalo->delete();

        $this->recalcular($producto, 'regalo-quitado', "Regalo quitado: {$nombre}. Las unidades vuelven al stock.", $accesorioId);
    }

    private function bloquearEditable(int $productoId): Producto
    {
        $producto = Producto::whereKey($productoId)->lockForUpdate()->firstOrFail();

        if ($producto->estaDadoDeBaja() || in_array($producto->estado, ProductoEstado::vendidos(), true)) {
            throw ValidationException::withMessages([
                'detalles' => "El equipo {$producto->imei} ya está vendido o dado de baja: sus regalos no se pueden cambiar.",
            ]);
        }

        // Todavia no se recibio: los regalos se le ponen cuando la compra se finaliza.
        if ($producto->estado === ProductoEstado::EnCompra->value) {
            throw ValidationException::withMessages([
                'detalles' => "El equipo {$producto->imei} es de una compra sin finalizar: los regalos se agregan después de finalizarla.",
            ]);
        }

        return $producto;
    }

    /**
     * anotar() ANTES del save de recalcularCosto(): el Observer escribe UNA fila
     * con el evento, la frase y el diff de costo_regalos / costo_total.
     */
    private function recalcular(Producto $producto, string $evento, string $descripcion, int $accesorioId): void
    {
        $producto->anotar($evento, $descripcion, ['accesorio_id' => $accesorioId]);
        $producto->recalcularCosto();
    }
}
