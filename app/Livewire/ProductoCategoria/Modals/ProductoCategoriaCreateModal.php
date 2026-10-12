<?php

namespace App\Livewire\ProductoCategoria\Modals;

use App\Models\ProductoCategoria;
use App\Models\ProductoMarca;
use Livewire\Component;
use Livewire\Attributes\On;

class ProductoCategoriaCreateModal extends Component
{
    public $openModal = false;
    public $productocategoria = [];

    protected $rules = [
        'productocategoria.nombre' => 'required|string|max:255',
        'productocategoria.producto_marca_id' => 'required',
    ];

    protected $messages = [
        'productocategoria.nombre' => 'Debe ingresar un nombre',
        'productocategoria.producto_marca_id' => 'Debe ingresar una marca',
    ];

    #[On('openProductoCategoriaCreateModal')]
    public function openModal()
    {
        $this->openModal = true;
    }

    public function store()
    {
        $this->validate();
        ProductoCategoria::create($this->productocategoria);
        $this->dispatch('refreshProductoCategoriaTable');
        toastr()->success('Categoria creada exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }

    public function render()
    {
        $marcas = ProductoMarca::all();
        return view('livewire.producto-categoria.modals.producto-categoria-create-modal', compact('marcas'));
    }
}
