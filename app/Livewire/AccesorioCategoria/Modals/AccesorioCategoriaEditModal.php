<?php

namespace App\Livewire\AccesorioCategoria\Modals;

use App\Models\AccesorioCategoria;
use App\Models\ProductoMarca;
use Livewire\Component;
use Livewire\Attributes\On;

class AccesorioCategoriaEditModal extends Component
{

    public $openModal = false;
    public $accesoriocategoria = [];

    protected $rules = [
        'accesoriocategoria.nombre' => 'required|string|max:255',
        'accesoriocategoria.descripcion' => 'nullable|string|max:255',
    ];

    protected $messages = [
        'accesoriocategoria.nombre' => 'Debe ingresar un nombre',
        'accesoriocategoria.descripcion' => 'Debe ingresar una descripcion',
    ];

    #[On('openAccesorioCategoriaEditModal')]
    public function openModal($id)
    {
        $accesoriocategoria = AccesorioCategoria::find($id);
        $this->accesoriocategoria = $accesoriocategoria->toArray();
        $this->openModal = true;
    }

    public function update()
    {
        $this->validate();

        $accesoriocategoria = AccesorioCategoria::find($this->accesoriocategoria['id']);
        $accesoriocategoria->update($this->accesoriocategoria);
        $this->dispatch('refreshAccesorioCategoriaTable');
        toastr()->success('Categoria editada exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }
    public function render()
    {
        return view('livewire.accesorio-categoria.modals.accesorio-categoria-edit-modal');
    }
}
