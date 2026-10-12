<?php

namespace App\Livewire\ProductoMarca;

use Livewire\Component;

class ProductoMarcaIndex extends Component
{
    public function openProductoMarcaCreateModal()
    {
        $this->dispatch('openProductoMarcaCreateModal');
    }
    public function render()
    {
        return view('livewire.producto-marca.producto-marca-index');
    }
}
