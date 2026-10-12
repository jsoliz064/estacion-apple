<?php

namespace App\Livewire\Tecnico\Modals;

use App\Models\Tecnicos;
use Livewire\Component;
use Livewire\Attributes\On;


class TecnicoDestroyModal extends Component
{
    public $openModal = false;
    public $tecnico;

    #[On('openTecnicoDestroyModal')]
    public function openModal($id)
    {
        $this->tecnico = Tecnicos::find($id);
        $this->openModal = true;
    }

    public function destroy()
    {
        // Con reparaciones no se borra: la FK las dejaria sin tecnico en
        // silencio, y sus comisiones (RESTRICT) lo impedirian con un error.
        if ($this->tecnico->reparaciones()->exists() || $this->tecnico->comisiones()->exists()) {
            toastr()->error('El técnico tiene reparaciones y comisiones registradas: no se puede eliminar.');
            return;
        }

        try {
            $this->tecnico->delete();
            $this->dispatch('refreshTecnicoTable');
            toastr()->success('Técnico eliminado exitosamente');
            $this->reset();
        } catch (\Throwable $th) {
            toastr()->error('Error al eliminar el técnico');
        }
    }

    public function closeModal()
    {
        $this->reset();
    }
    public function render()
    {
        return view('livewire.tecnico.modals.tecnico-destroy-modal');
    }
}
