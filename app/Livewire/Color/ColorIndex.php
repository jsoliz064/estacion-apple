<?php

namespace App\Livewire\Color;

use Livewire\Component;

class ColorIndex extends Component
{
    public function openColorCreateModal()
    {
        $this->dispatch('openColorFormModal');
    }

    public function render()
    {
        return view('livewire.color.color-index');
    }
}
