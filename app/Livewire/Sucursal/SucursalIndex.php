<?php

namespace App\Livewire\Sucursal;

use Livewire\Component;

class SucursalIndex extends Component
{
    public function openSucursalCreateModal()
    {
        $this->dispatch('openSucursalCreateModal');
    }

    public function render()
    {
        return view('livewire.sucursal.sucursal-index');
    }
}
