<?php

namespace App\Livewire\User\Modals;

use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\On;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserEditModal extends Component
{
    public $openModal = false;
    public $user = [];
    public $email = null;

    protected $rules = [
        'user.name' => 'required|string|max:255',
        'user.rol_id' => 'required',
        'email' => 'required|email',
        'user.comision_porcentaje' => 'required|numeric|min:0|max:100',
        'user.activo' => 'boolean',
    ];

    protected $messages = [
        'user.name.required' => 'Debe ingresar un nombre',
        'user.rol_id.required' => 'Debe seleccionar un rol',
        'email.required' => 'Debe ingresar un correo electronico',
        'email.email' => 'Debe ingresar un correo electronico valido',
        'user.comision_porcentaje.required' => 'Debe ingresar el porcentaje de comisión (0 si no cobra comisión)',
        'user.comision_porcentaje.numeric' => 'La comisión debe ser un número',
        'user.comision_porcentaje.min' => 'La comisión no puede ser negativa',
        'user.comision_porcentaje.max' => 'La comisión no puede pasar de 100',
    ];

    public function render()
    {
        $roles = Role::get();
        return view('livewire.user.modals.user-edit-modal', compact('roles'));
    }

    #[On('openUserEditModal')]
    public function openModal($id)
    {
        $user = User::find($id);
        $this->user = $user->toArray();
        $this->email = $this->user['email'];
        $this->user['password'] = "";
        $this->user['cpassword'] = "";
        $this->user['rol_id'] = $user->rol_id();
        $this->user['activo'] = (bool) $user->activo;
        $this->openModal = true;
    }

    /** Si el formulario intenta desactivar a alguien que estaba activo. */
    private function desactiva(User $user): bool
    {
        return $user->activo && !($this->user['activo'] ?? true);
    }

    public function update()
    {
        $this->validate();

        $user = User::find($this->user['id']);

        if ($this->email !== $user->email) {
            $this->validate([
                'email' => 'required|string|max:255|email|unique:users',
            ], [
                'email.unique' => 'El correo electronico ingresado ya se encuentra registrado',
            ]);
        }

        if ($this->desactiva($user)) {
            // Desactivar saca al usuario del sistema: tiene su propio permiso,
            // aparte de poder editar nombres y correos.
            if (!Auth::user()?->can('user.desactivar')) {
                $this->addError('user.activo', 'No tienes permiso para desactivar usuarios.');
                return;
            }
            if ($user->id === Auth::id()) {
                $this->addError('user.activo', 'No puedes desactivar tu propio usuario.');
                return;
            }
        }

        if ($this->user['password'] !== "") {
            if ($this->user['password'] !== $this->user['cpassword']) {
                $this->validate([
                    'user.password' => 'required|string|min:4',
                    'user.cpassword' => 'required|string|min:4|confirmed',
                ], [
                    'user.password.min' => 'Debe ingresar una contraseña de al menos 4 caracteres',
                    'user.cpassword.confirmed' => 'Las contraseñas no coinciden',
                ]);
            }
            $this->user['password'] = Hash::make($this->user['password']);
            $user->password = $this->user['password'];
        }
        $desactiva = $this->desactiva($user);
        $this->user['email'] = $this->email;
        $user->name = $this->user['name'];
        $user->email = $this->user['email'];
        $user->comision_porcentaje = $this->user['comision_porcentaje'];
        $user->activo = (bool) ($this->user['activo'] ?? true);
        $user->roles()->sync($this->user['rol_id']);
        $user->save();

        // Ademas de guardar el flag, cierra sus sesiones abiertas ya mismo.
        if ($desactiva) {
            $user->desactivar();
        }

        $this->dispatch('refreshUserTable');
        toastr()->success('Usuario editado exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }
}
