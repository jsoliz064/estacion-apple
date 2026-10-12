<?php

namespace App\Livewire\User\Modals;

use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\On;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserCreateModal extends Component
{
    public $openModal = false;
    public $user = [];
    public $email = null;

    protected $rules = [
        'user.name' => 'required|string|max:255',
        'user.password' => 'required|string|min:4',
        'user.cpassword' => 'required|string|min:4',
        'email' => 'required|string|max:255|email|unique:users',
        'user.rol_id' => 'required',
        'user.comision_porcentaje' => 'required|numeric|min:0|max:100',
        'user.activo' => 'boolean',
    ];

    protected $messages = [
        'user.name.required' => 'Debe ingresar un nombre',
        'user.password.required' => 'Debe ingresar una contraseña',
        'user.password.min' => 'La contraseña debe tener al menos 4 caracteres.',
        'user.cpassword.required' => 'Debe confirmar la contraseña',
        'user.rol_id.required' => 'Debe seleccionar un rol',
        'email.required' => 'Debe ingresar un correo electronico',
        'email.email' => 'Debe ingresar un correo electronico valido',
        'email.unique' => 'El correo electronico ingresado ya se encuentra registrado',
        'user.comision_porcentaje.required' => 'Debe ingresar el porcentaje de comisión (0 si no cobra comisión)',
        'user.comision_porcentaje.numeric' => 'La comisión debe ser un número',
        'user.comision_porcentaje.min' => 'La comisión no puede ser negativa',
        'user.comision_porcentaje.max' => 'La comisión no puede pasar de 100',
    ];

    public function render()
    {
        $roles = Role::get();
        return view('livewire.user.modals.user-create-modal', compact('roles'));
    }

    #[On('openUserCreateModal')]
    public function openModal()
    {
        $this->reset();
        $this->user['comision_porcentaje'] = 0;
        $this->user['activo'] = true;
        $this->openModal = true;
    }

    public function store()
    {
        $this->validate();

        if ($this->user['password'] !== $this->user['cpassword']) {
            $this->validate([
                'user.cpassword' => 'required|string|min:4|confirmed',
            ]);
        }
        $this->user['email'] = $this->email;
        $this->user['password'] = Hash::make($this->user['password']);
        $user = User::create($this->user);
        $user->roles()->sync($this->user['rol_id']);
        $this->dispatch('refreshUserTable');
        toastr()->success('Usuario creado exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }

}
