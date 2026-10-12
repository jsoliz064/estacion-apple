<?php

namespace App\Livewire\Proveedor\Modals;

use App\Models\Proveedor;
use Livewire\Component;
use Livewire\Attributes\On;


class ProveedorCreateModal extends Component
{
    public $openModal = false;
    public $proveedor = [];

    protected $rules = [
        'proveedor.nombre' => 'required|string|max:255',
    ];

    protected $messages = [
        'proveedor.nombre' => 'Debe ingresar un nombre',
    ];


    #[On('openProveedorCreateModal')]
    public function openModal()
    {
        $this->openModal = true;
    }

    public function store()
    {
        $this->validate();
        $proveedor = Proveedor::create($this->proveedor);
        $this->dispatch('refreshProveedorTable');
        toastr()->success('Proveedor creado exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }
    public function render()
    {
        return view('livewire.proveedor.modals.proveedor-create-modal');
    }
}
