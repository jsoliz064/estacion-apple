<?php

namespace App\Livewire\AccesorioCategoria\Modals;

use App\Models\AccesorioCategoria;
use Livewire\Component;
use Livewire\Attributes\On;

class AccesorioCategoriaCreateModal extends Component
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

    #[On('openAccesorioCategoriaCreateModal')]
    public function openModal()
    {
        $this->openModal = true;
    }

    public function store()
    {
        $this->validate();
        AccesorioCategoria::create($this->accesoriocategoria);
        $this->dispatch('refreshAccesorioCategoriaTable');
        toastr()->success('Categoria creada exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.accesorio-categoria.modals.accesorio-categoria-create-modal');
    }
}
