<?php

namespace App\Livewire\RepuestoCategoria\Modals;

use App\Models\RepuestoCategoria;
use Livewire\Component;
use Livewire\Attributes\On;

class RepuestoCategoriaCreateModal extends Component
{
    public $openModal = false;
    public $repuestocategoria = [];

    protected $rules = [
        'repuestocategoria.nombre' => 'required|string|max:255',
        'repuestocategoria.descripcion' => 'nullable|string|max:255',
    ];

    protected $messages = [
        'repuestocategoria.nombre' => 'Debe ingresar un nombre',
        'repuestocategoria.descripcion' => 'Debe ingresar una descripcion',
    ];

    #[On('openRepuestoCategoriaCreateModal')]
    public function openModal()
    {
        $this->openModal = true;
    }

    public function store()
    {
        $this->validate();
        RepuestoCategoria::create($this->repuestocategoria);
        $this->dispatch('refreshRepuestoCategoriaTable');
        toastr()->success('Categoria creada exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.repuesto-categoria.modals.repuesto-categoria-create-modal');
    }
}
