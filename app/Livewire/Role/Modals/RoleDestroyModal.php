<?php

namespace App\Livewire\Role\Modals;

use Livewire\Component;
use Livewire\Attributes\On;
use Spatie\Permission\Models\Role;

class RoleDestroyModal extends Component
{
    public $openModal = false;
    public $role;

    public function render()
    {
        return view('livewire.role.modals.role-destroy-modal');
    }

    #[On('openRoleDestroyModal')]
    public function openModal($id)
    {
        $this->role = Role::find($id);
        $this->openModal = true;
    }

    public function destroy()
    {
        try {
            $this->role->delete();
            $this->dispatch('refreshRoleTable');
            toastr()->success('Rol eliminado exitosamente');
            $this->reset();
        } catch (\Throwable $th) {
            toastr()->error('Error al eliminar el rol');
        }
    }

    public function closeModal()
    {
        $this->reset();
    }
}
