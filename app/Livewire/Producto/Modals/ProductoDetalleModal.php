<?php

namespace App\Livewire\Producto\Modals;

use App\Enums\ReparacionTipo;
use App\Models\Producto;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * El detalle de un equipo, de solo lectura: de donde vino (compra o permuta), a
 * quien se vendio y su garantia, si tiene una garantia o un trabajo externo en
 * curso y su baja.
 *
 * Antes eran columnas de la tabla de Productos (Compra, Venta, Baja, Garantia,
 * Trabajo Externo): estorbaban en el celular y casi siempre decian «-». Pedido
 * de la tienda: verlo aparte, desde «Ver detalle» del menu de la fila.
 */
class ProductoDetalleModal extends Component
{
    public bool $openModal = false;

    #[Locked]
    public ?int $productoId = null;

    #[On('openProductoDetalleModal')]
    public function openModal($id): void
    {
        abort_unless(Auth::user()?->can('producto.detalle'), 403);

        $this->productoId = (int) $id;
        $this->openModal = true;
    }

    public function closeModal(): void
    {
        $this->openModal = false;
    }

    public function render()
    {
        // Las relaciones en render(): Livewire no las rehidrata.
        $producto = $this->openModal && $this->productoId
            ? Producto::with([
                'modelo', 'sucursal', 'bajaUser',
                'compraDetalle.compra.proveedor',
                'permuta',
                'ventaDetalle.venta.fichaCliente',
            ])->find($this->productoId)
            : null;

        $pendientes = $producto
            ? $producto->reparaciones()->where('estado', 'Pendiente')
                ->whereIn('tipo', [ReparacionTipo::Garantia->value, ReparacionTipo::Externo->value])
                ->with('tecnico')->orderBy('id')->get()
            : collect();

        return view('livewire.producto.modals.producto-detalle-modal', [
            'producto' => $producto,
            'pendientes' => $pendientes,
        ]);
    }
}
