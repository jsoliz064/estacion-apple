<?php

namespace App\Livewire\RepuestoCategoria;

use Livewire\Component;

class RepuestoCategoriaIndex extends Component
{

    public function render()
    {
        return view('livewire.repuesto-categoria.repuesto-categoria-index');
    }

    public function openRepuestoCategoriaCreateModal()
    {
        $this->dispatch('openRepuestoCategoriaCreateModal');
    }
}
