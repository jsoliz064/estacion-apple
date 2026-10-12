<?php

namespace App\Livewire\AccesorioCategoria\Modals;

use App\Models\AccesorioCategoria;
use Livewire\Component;
use Livewire\Attributes\On;

class AccesorioCategoriaDestroyModal extends Component
{
    public $openModal = false;
    public $accesoriocategoria;

    #[On('openAccesorioCategoriaDestroyModal')]
    public function openModal($id)
    {
        $this->accesoriocategoria = AccesorioCategoria::find($id);
        $this->openModal = true;
    }

    public function destroy()
    {
        try {
            $this->accesoriocategoria->delete();
            $this->dispatch('refreshAccesorioCategoriaTable');
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
        return view('livewire.accesorio-categoria.modals.accesorio-categoria-destroy-modal');
    }
}
