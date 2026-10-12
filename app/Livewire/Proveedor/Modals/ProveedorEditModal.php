<?php

namespace App\Livewire\Proveedor\Modals;

use App\Models\Proveedor;
use Livewire\Component;
use Livewire\Attributes\On;


class ProveedorEditModal extends Component
{
    public $openModal = false;
    public $proveedor = [];

    protected $rules = [
        'proveedor.nombre' => 'required|string|max:255',
    ];

    protected $messages = [
        'proveedor.nombre' => 'Debe ingresar un nombre',
    ];

    #[On('openProveedorEditModal')]
    public function openModal($id)
    {
        $proveedor = Proveedor::find($id);
        $this->proveedor = $proveedor->toArray();
        $this->openModal = true;
    }

    public function update()
    {
        $this->validate();
        $proveedor = Proveedor::find($this->proveedor['id']);
        $proveedor->nombre = $this->proveedor['nombre'];
        $proveedor->save();
        $this->dispatch('refreshProveedorTable');
        toastr()->success('Proveedor editado exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }


    public function render()
    {
        return view('livewire.proveedor.modals.proveedor-edit-modal');
    }
}
