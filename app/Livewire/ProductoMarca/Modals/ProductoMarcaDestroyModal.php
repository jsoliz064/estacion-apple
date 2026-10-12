<?php

namespace App\Livewire\ProductoMarca\Modals;

use App\Models\ProductoMarca;
use Livewire\Component;
use Livewire\Attributes\On;

class ProductoMarcaDestroyModal extends Component
{
    public $openModal = false;
    public $productomarca;

    #[On('openProductoMarcaDestroyModal')]
    public function openModal($id)
    {
        $this->productomarca = ProductoMarca::find($id);
        $this->openModal = true;
    }

    public function destroy()
    {
        try {
            $this->productomarca->delete();
            $this->dispatch('refreshProductoMarcaTable');
            toastr()->success('Marca eliminado exitosamente');
            $this->reset();
        } catch (\Throwable $th) {
            toastr()->error('Error al eliminar el técnico');
        }
    }

    public function closeModal()
    {
        $this->reset();
    }
    public function render()
    {
        return view('livewire.producto-marca.modals.producto-marca-destroy-modal');
    }
}
