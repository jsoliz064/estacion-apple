<?php

namespace App\Livewire\Cliente;

use Livewire\Component;

class ClienteIndex extends Component
{
    public function render()
    {
        return view('livewire.cliente.cliente-index');
    }

    public function openClienteCreateModal()
    {
        $this->dispatch('openClienteCreateModal');
    }
}
