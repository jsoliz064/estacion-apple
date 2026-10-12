<?php

namespace App\Livewire\Proveedor\Modals;

use App\Models\Proveedor;
use Livewire\Component;
use Livewire\Attributes\On;


class ProveedorDestroyModal extends Component
{

    public $openModal = false;
    public $proveedor;
    
    #[On('openProveedorDestroyModal')]
    public function openModal($id)
    {
        $this->proveedor = Proveedor::find($id);
        $this->openModal = true;
    }

    public function destroy()
    {
        try {
            $this->proveedor->delete();
            $this->dispatch('refreshProveedorTable');
            toastr()->success('Proveedor eliminado exitosamente');
            $this->reset();
        } catch (\Throwable $th) {
            toastr()->error('Error al eliminar el proveedor');
        }
    }

    public function closeModal()
    {
        $this->reset();
    }
    public function render()
    {
        return view('livewire.proveedor.modals.proveedor-destroy-modal');
    }
}
