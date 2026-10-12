<?php

namespace App\Livewire\ProductoModelo\Modals;

use App\Models\ProductoModelo;
use Livewire\Component;
use Livewire\Attributes\On;

class ProductoModeloDestroyModal extends Component
{
    public $openModal = false;
    public $productomodelo;

    #[On('openProductoModeloDestroyModal')]
    public function openModal($id)
    {
        $this->productomodelo = ProductoModelo::find($id);
        $this->openModal = true;
    }

    public function destroy()
    {
        try {
            $this->productomodelo->delete();
            $this->dispatch('refreshProductoModeloTable');
            toastr()->success('Modelo eliminado exitosamente');
            $this->reset();
        } catch (\Throwable $th) {
            toastr()->error('Error al eliminar el modelo');
        }
    }

    public function closeModal()
    {
        $this->reset();
    }
    public function render()
    {
        return view('livewire.producto-modelo.modals.producto-modelo-destroy-modal');
    }
}
