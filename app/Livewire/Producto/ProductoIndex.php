<?php

namespace App\Livewire\Producto;

use App\Enums\ProductoEstado;
use App\Exports\ProductoModeloExport;
use App\Models\Producto;
use App\Models\Sucursal;
use Livewire\Component;

class ProductoIndex extends Component
{
    public $sucursalesResumen = [];
    public $totalProductos = 0;
    public $totalInventario = 0;
    public $totalInventarioVendido = 0;

    public function mount()
    {
        $this->loadResumen();
        // Lo que sigue siendo stock: ni vendido (ni a credito) ni dado de baja.
        // Un roto se valora a su costo: antes era costo + 60, un margen fijo en
        // USD de la importadora que en Bs no significa nada. Agregado en SQL,
        // sin traer filas a PHP.
        $resumen = Producto::query()
            ->vigentes()
            ->whereNotIn('estado', ProductoEstado::vendidos())
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(costo_total), 0) as costo')
            ->selectRaw('COALESCE(SUM(CASE WHEN estado = ? THEN costo_total ELSE precio_vendedor END), 0) as venta', [ProductoEstado::Roto->value])
            ->toBase()
            ->first();
        $this->totalProductos = (int) $resumen->total;
        $this->totalInventario = (float) $resumen->costo;
        $this->totalInventarioVendido = (float) $resumen->venta;
    }

    /**
     * Cuantos productos no vendidos tiene cada sucursal, por estado.
     *
     * El conteo se hace en SQL. Antes era un eager load de la relacion completa
     * y un groupBy() de Collection: traia a PHP TODAS las filas de productos no
     * vendidos -- con sus treinta columnas, imagenes incluidas en el modelo --
     * solo para contarlas. Son dos consultas agregadas y ningun modelo en
     * memoria.
     *
     * Se muestran las sucursales activas y, de las desactivadas, solo las que
     * todavia guardan equipos: esos equipos existen y no pueden desaparecer del
     * resumen. Antes habia un `where('id', '<>', 5)` sin explicacion que en la
     * base vieja no excluia nada; en la nueva habria escondido la quinta
     * sucursal real que diera de alta el negocio.
     */
    public function loadResumen()
    {
        $conteos = Producto::query()
            ->vigentes()
            ->whereNotIn('estado', ProductoEstado::vendidos())
            ->selectRaw('sucursal_id, estado, COUNT(*) as total')
            ->groupBy('sucursal_id', 'estado')
            ->get()
            ->groupBy('sucursal_id');

        // Ordenado por nombre: el filtro de sucursal de ProductoTable ya usa
        // orderBy('nombre') y este card no, asi que las dos listas salian en
        // orden distinto.
        $this->sucursalesResumen = Sucursal::orderBy('nombre')
            ->get()
            ->filter(fn($sucursal) => $sucursal->activa || isset($conteos[$sucursal->id]))
            ->values()
            ->map(fn($sucursal) => [
                'nombre' => $sucursal->nombre,
                'resumen' => ($conteos[$sucursal->id] ?? collect())
                    ->pluck('total', 'estado')
                    ->map(fn($total) => (int) $total),
            ]);
    }

    public function render()
    {
        return view('livewire.producto.producto-index');
    }

    public function openProductoCreateModal()
    {
        $this->dispatch('openProductoCreateModal');
    }

    public function openProductoEditSucursalModal()
    {
        $this->dispatch('openProductoEditSucursalModal');
    }

    public function openProductoEstadoMasivoModal()
    {
        $this->dispatch('openProductoEstadoMasivoModal');
    }

    public function exportProductosExcel()
    {
        $now = date('Hi');
        return (new ProductoModeloExport())
            ->download("productos-modelos-{$now}.xlsx");
    }
}
