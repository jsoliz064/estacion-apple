<?php

namespace App\Livewire\RepuestoCategoria\Modals;

use App\Models\RepuestoCategoria;
use Livewire\Component;
use Livewire\Attributes\On;

class RepuestoCategoriaDestroyModal extends Component
{
    public $openModal = false;
    public $repuestocategoria;

    #[On('openRepuestoCategoriaDestroyModal')]
    public function openModal($id)
    {
        $this->repuestocategoria = RepuestoCategoria::find($id);
        $this->openModal = true;
    }

    public function destroy()
    {
        try {
            $this->repuestocategoria->delete();
            $this->dispatch('refreshRepuestoCategoriaTable');
            toastr()->success('Categoria eliminada exitosamente');
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
        return view('livewire.repuesto-categoria.modals.repuesto-categoria-destroy-modal');
    }
}
