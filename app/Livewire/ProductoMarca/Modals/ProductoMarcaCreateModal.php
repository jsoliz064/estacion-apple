<?php

namespace App\Livewire\ProductoMarca\Modals;

use App\Models\ProductoMarca;
use Livewire\Component;
use Livewire\Attributes\On;

class ProductoMarcaCreateModal extends Component
{
    public $openModal = false;
    public $productomarca = [];

    protected $rules = [
        'productomarca.nombre' => 'required|string|max:255',
    ];

    protected $messages = [
        'productomarca.nombre' => 'Debe ingresar un nombre',
    ];

    #[On('openProductoMarcaCreateModal')]
    public function openModal()
    {
        $this->openModal = true;
    }

    public function store()
    {
        $this->validate();


        $productomarca = ProductoMarca::create($this->productomarca);
        $this->dispatch('refreshProductoMarcaTable');
        toastr()->success('Marca creada exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.producto-marca.modals.producto-marca-create-modal');
    }
}
