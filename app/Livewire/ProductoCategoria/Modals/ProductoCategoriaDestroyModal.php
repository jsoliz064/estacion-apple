<?php

namespace App\Livewire\ProductoCategoria\Modals;

use App\Models\ProductoCategoria;
use Livewire\Component;
use Livewire\Attributes\On;

class ProductoCategoriaDestroyModal extends Component
{
    public $openModal = false;
    public $productocategoria;

    #[On('openProductoCategoriaDestroyModal')]
    public function openModal($id)
    {
        $this->productocategoria = ProductoCategoria::find($id);
        $this->openModal = true;
    }

    public function destroy()
    {
        try {
            $this->productocategoria->delete();
            $this->dispatch('refreshProductoCategoriaTable');
            toastr()->success('Categoria eliminado exitosamente');
            $this->reset();
        } catch (\Throwable $th) {
            toastr()->error('Error al eliminar la categoria');
        }
    }

    public function closeModal()
    {
        $this->reset();
    }
    public function render()
    {
        return view('livewire.producto-categoria.modals.producto-categoria-destroy-modal');
    }
}
