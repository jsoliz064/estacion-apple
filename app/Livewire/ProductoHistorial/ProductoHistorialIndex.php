<?php

namespace App\Livewire\ProductoHistorial;

use App\Enums\ReparacionTipo;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ProductoHistorialIndex extends Component
{
    public $producto;

    public function mount($producto_id)
    {
        // findOrFail y no find(): con un id inexistente el blade reventaba mas
        // adelante al leer $producto->imei sobre null.
        $this->producto = Producto::findOrFail($producto_id);
    }

    public function render()
    {
        // Las relaciones en render(): Livewire rehidrata $producto sin ellas.
        $this->producto->load([
            'modelo', 'sucursal', 'bajaUser',
            'compraDetalle.compra.proveedor',
            'permuta',
            'ventaDetalle.venta.user',
            'ventaDetalle.venta.fichaCliente',
            'regalos.accesorio', 'regalos.sucursal',
        ]);

        return view('livewire.producto-historial.index', [
            'resumenReparaciones' => $this->resumenReparaciones(),
        ]);
    }

    /**
     * Cifras de referencia de las reparaciones de este producto, todas en Bs.
     *
     * Van aqui y no en la tabla a proposito: son FIJAS, no se mueven con los
     * filtros de la pestana. El pie de la tabla dice "esto es lo que estoy
     * mirando"; estas tarjetas dicen "esto es lo que el telefono lleva gastado".
     *
     * inventario replica el criterio EXACTO de Producto::recalcularCosto()
     * -SUM(costo_total) de tipo != Externo- para poder compararlo con lo que
     * quedo escrito en productos.costo_reparacion.
     */
    protected function resumenReparaciones(): object
    {
        $externo = ReparacionTipo::Externo->value;

        return DB::table('productos_reparaciones')
            ->where('producto_id', $this->producto->id)
            ->selectRaw('COUNT(*) as reparaciones')
            ->selectRaw("COALESCE(SUM(estado = 'Pendiente'), 0) as pendientes")
            ->selectRaw('COALESCE(SUM(costo_total), 0) as total')
            ->selectRaw('COALESCE(SUM(CASE WHEN tipo <> ? THEN costo_total ELSE 0 END), 0) as inventario', [$externo])
            ->selectRaw('COALESCE(SUM(CASE WHEN tipo =  ? THEN cobro_cliente ELSE 0 END), 0) as cobrado_externo', [$externo])
            ->first();
    }
}
