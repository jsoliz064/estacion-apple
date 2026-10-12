<?php

namespace App\Livewire\ProductoModelo;

use Livewire\Component;

class ProductoModeloIndex extends Component
{
    public function openProductoModeloCreateModal()
    {
        $this->dispatch('openProductoModeloCreateModal');
    }
    public function render()
    {
        return view('livewire.producto-modelo.producto-modelo-index');
    }
}
