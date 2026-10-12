<?php

namespace App\Livewire\Proveedor;

use Livewire\Component;

class ProveedorIndex extends Component
{
    public function openProveedorCreateModal()
    {
        $this->dispatch('openProveedorCreateModal');
    }

    public function render()
    {
        return view('livewire.proveedor.proveedor-index');
    }
}
