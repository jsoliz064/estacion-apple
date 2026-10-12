<?php

namespace App\Livewire\Role\Modals;

use Livewire\Component;
use Livewire\Attributes\On;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleEditModal extends Component
{
    public $openModal = false;
    public $role = [];
    public $roleModel;
    public $selectedPermissions = [];
    public $permissions;

    protected $rules = [
        'role.name' => 'required|string|max:255',
    ];

    protected $messages = [
        'role.name.required' => 'Debe ingresar un nombre',
    ];

    public function render()
    {
        return view('livewire.role.modals.role-edit-modal');
    }

    public function __construct()
    {
        $this->permissions = Permission::all();
    }

    #[On('openRoleEditModal')]
    public function openModal($id)
    {
        $role = Role::with('permissions')->find($id);
        $this->roleModel = $role;
        $this->role = $role->toArray();
        $this->selectedPermissions = $role->permissions->pluck('id')->toArray();
        $this->openModal = true;
    }

    public function update()
    {
        $this->validate();

        $role = Role::find($this->role['id']);
        $role->name = $this->role['name'];
        $role->save();
        $permissions = Permission::whereIn('id', $this->selectedPermissions)->get();
        $role->syncPermissions($permissions);
        $this->dispatch('refreshRoleTable');
        toastr()->success('Rol editado exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }
}
