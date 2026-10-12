<?php

namespace App\Livewire\ProductoCategoria\Modals;

use App\Models\ProductoCategoria;
use App\Models\ProductoMarca;
use Livewire\Component;
use Livewire\Attributes\On;

class ProductoCategoriaEditModal extends Component
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

    #[On('openProductoCategoriaEditModal')]
    public function openModal($id)
    {
        $productocategoria = ProductoCategoria::find($id);
        $this->productocategoria = $productocategoria->toArray();
        $this->openModal = true;
    }

    public function update()
    {
        $this->validate();

        $productocategoria = ProductoCategoria::find($this->productocategoria['id']);
        $productocategoria->nombre = $this->productocategoria['nombre'];
        $productocategoria->save();
        $this->dispatch('refreshProductoCategoriaTable');
        toastr()->success('Categoria editada exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }
    public function render()
    {
        $marcas = ProductoMarca::all();
        return view('livewire.producto-categoria.modals.producto-categoria-edit-modal', compact('marcas'));
    }
}
