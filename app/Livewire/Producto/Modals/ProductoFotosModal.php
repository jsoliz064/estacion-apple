<?php

namespace App\Livewire\Producto\Modals;

use App\Models\Producto;
use App\Models\ProductoImagen;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * El visor de las fotos de un equipo, a pantalla completa: lo abre la miniatura
 * de las tablas (Productos y el detalle de la compra). Solo muestra; las fotos se
 * sacan y se quitan en el modal de edicion.
 */
class ProductoFotosModal extends Component
{
    public bool $openModal = false;

    public ?int $productoId = null;

    #[On('openProductoFotosModal')]
    public function open(int $id): void
    {
        // Lo montan dos pantallas, cada una con su permiso.
        abort_unless(auth()->user()?->canAny(['producto.index', 'compra.detalle']), 403);

        $this->productoId = $id;
        $this->openModal = true;
    }

    public function closeModal(): void
    {
        $this->reset('openModal', 'productoId');
    }

    public function render()
    {
        $producto = null;
        $fotos = [];

        if ($this->openModal) {
            $producto = Producto::with('modelo')->find($this->productoId);
            $fotos = ProductoImagen::where('producto_id', $this->productoId)
                ->orderBy('id')->get()
                ->map->url()->all();
        }

        return view('livewire.producto.modals.producto-fotos-modal', [
            'producto' => $producto,
            'fotos' => $fotos,
        ]);
    }
}
