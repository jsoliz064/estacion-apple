<?php

namespace App\Livewire\Role\Modals;

use Livewire\Component;
use Livewire\Attributes\On;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleCreateModal extends Component
{
    public $openModal = false;
    public $role = [];
    public $selectedPermissions = [];
    public $permissions;

    protected $rules = [
        'role.name' => 'required|string|max:255',
    ];

    protected $messages = [
        'role.name.required' => 'Debe ingresar un nombre',
    ];

    public function __construct()
    {
        $this->permissions = Permission::all();
        
    }

    public function render()
    {
        return view('livewire.role.modals.role-create-modal');
    }

    #[On('openRoleCreateModal')]
    public function openModal()
    {
        $this->openModal = true;
    }

    public function store()
    {
        $this->validate();
        $this->role['guard_name'] = "web";
        $role = Role::create($this->role);
        $permissions = Permission::whereIn('id', $this->selectedPermissions)->get();
        $role->syncPermissions($permissions);
        $this->dispatch('refreshRoleTable');
        toastr()->success('Rol creado exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }
}
