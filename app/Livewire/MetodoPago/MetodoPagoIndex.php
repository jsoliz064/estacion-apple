<?php

namespace App\Livewire\MetodoPago;

use Livewire\Component;

class MetodoPagoIndex extends Component
{
    public function openMetodoPagoCreateModal()
    {
        $this->dispatch('openMetodoPagoFormModal');
    }

    public function render()
    {
        return view('livewire.metodo-pago.metodo-pago-index');
    }
}
