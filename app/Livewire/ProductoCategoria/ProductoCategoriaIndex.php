<?php

namespace App\Livewire\ProductoCategoria;

use Livewire\Component;

class ProductoCategoriaIndex extends Component
{

    public function render()
    {
        return view('livewire.producto-categoria.producto-categoria-index');
    }

    public function openProductoCategoriaCreateModal()
    {
        $this->dispatch('openProductoCategoriaCreateModal');
    }
}
