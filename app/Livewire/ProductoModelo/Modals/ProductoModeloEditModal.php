<?php

namespace App\Livewire\ProductoModelo\Modals;

use App\Models\ProductoCategoria;
use App\Models\ProductoModelo;
use Livewire\Component;
use Livewire\Attributes\On;

class ProductoModeloEditModal extends Component
{

    public $openModal = false;
    public $productomodelo = [];

    protected $rules = [
        'productomodelo.nombre' => 'required|string|max:255',
        'productomodelo.producto_categoria_id' => 'required',
    ];

    protected $messages = [
        'productomodelo.nombre' => 'Debe ingresar un nombre',
        'productomodelo.producto_categoria_id' => 'Debe ingresar una categoria',
    ];

    #[On('openProductoModeloEditModal')]
    public function openModal($id)
    {
        $productomodelo = ProductoModelo::find($id);
        $this->productomodelo = $productomodelo->toArray();
        $this->openModal = true;
    }

    public function update()
    {
        $this->validate();

        $productomodelo = ProductoModelo::find($this->productomodelo['id']);
        $productomodelo->nombre = $this->productomodelo['nombre'];
        $productomodelo->save();
        $this->dispatch('refreshProductoModeloTable');
        toastr()->success('Modelo editado exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }
    public function render()
    {
        $categorias = ProductoCategoria::all();
        return view('livewire.producto-modelo.modals.producto-modelo-edit-modal', compact('categorias'));
    }
}
