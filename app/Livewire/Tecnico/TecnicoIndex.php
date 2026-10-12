<?php

namespace App\Livewire\Tecnico;

use Livewire\Component;

class TecnicoIndex extends Component
{
    public function openTecnicoCreateModal()
    {
        $this->dispatch('openTecnicoCreateModal');
    }
    public function render()
    {
        return view('livewire.tecnico.tecnico-index');
    }
}
