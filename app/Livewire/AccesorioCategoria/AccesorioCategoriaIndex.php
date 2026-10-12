<?php

namespace App\Livewire\AccesorioCategoria;

use Livewire\Component;

class AccesorioCategoriaIndex extends Component
{

    public function render()
    {
        return view('livewire.accesorio-categoria.accesorio-categoria-index');
    }

    public function openAccesorioCategoriaCreateModal()
    {
        $this->dispatch('openAccesorioCategoriaCreateModal');
    }
}
