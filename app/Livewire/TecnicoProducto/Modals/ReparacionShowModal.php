<?php

namespace App\Livewire\TecnicoProducto\Modals;

use App\Models\ProductoReparacion;
use Livewire\Component;
use Livewire\Attributes\On;

class ReparacionShowModal extends Component
{
    public $openModal = false;
    public $reparacion;

    public function render()
    {
        // Ver el comentario equivalente en ProductoHistorialModal: aqui y no
        // en openModal(), porque Livewire rehidrata el modelo sin relaciones.
        $this->reparacion?->load([
            'producto',
            'tecnico',
            'repuestos.repuesto.categoria',
            'repuestos.repuesto.modelo',
        ]);

        return view('livewire.tecnico-producto.modals.reparacion-show-modal');
    }

    #[On('openReparacionShowModal')]
    public function openModal($id)
    {
        $reparacion = ProductoReparacion::find($id);
        $this->reparacion = $reparacion;
        $this->openModal = true;
    }

    public function closeModal()
    {
        $this->reset();
    }
}
