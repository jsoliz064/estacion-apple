<?php

namespace App\Livewire\ProductoMarca\Modals;

use App\Models\ProductoMarca;
use Livewire\Component;
use Livewire\Attributes\On;

class ProductoMarcaEditModal extends Component
{

    public $openModal = false;
    public $productomarca = [];

    protected $rules = [
        'productomarca.nombre' => 'required|string|max:255',
    ];

    protected $messages = [
        'productomarca.nombre' => 'Debe ingresar un nombre',
    ];

    #[On('openProductoMarcaEditModal')]
    public function openModal($id)
    {
        $productomarca = ProductoMarca::find($id);
        $this->productomarca = $productomarca->toArray();
        $this->openModal = true;
    }

    public function update()
    {
        $this->validate();

        $productomarca = ProductoMarca::find($this->productomarca['id']);
        $productomarca->nombre = $this->productomarca['nombre'];
        $productomarca->save();
        $this->dispatch('refreshProductoMarcaTable');
        toastr()->success('Marca editada exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }
    public function render()
    {
        return view('livewire.producto-marca.modals.producto-marca-edit-modal');
    }
}
