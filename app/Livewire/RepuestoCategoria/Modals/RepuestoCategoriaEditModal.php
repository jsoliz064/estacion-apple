<?php

namespace App\Livewire\RepuestoCategoria\Modals;

use App\Models\RepuestoCategoria;
use App\Models\ProductoMarca;
use Livewire\Component;
use Livewire\Attributes\On;

class RepuestoCategoriaEditModal extends Component
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

    #[On('openRepuestoCategoriaEditModal')]
    public function openModal($id)
    {
        $repuestocategoria = RepuestoCategoria::find($id);
        $this->repuestocategoria = $repuestocategoria->toArray();
        $this->openModal = true;
    }

    public function update()
    {
        $this->validate();

        $repuestocategoria = RepuestoCategoria::find($this->repuestocategoria['id']);
        $repuestocategoria->update($this->repuestocategoria);
        $this->dispatch('refreshRepuestoCategoriaTable');
        toastr()->success('Categoria editada exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }
    public function render()
    {
        return view('livewire.repuesto-categoria.modals.repuesto-categoria-edit-modal');
    }
}
