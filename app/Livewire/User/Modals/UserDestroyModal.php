<?php

namespace App\Livewire\User\Modals;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\On;

/**
 * Eliminar un usuario, o desactivarlo si ya tiene movimientos.
 *
 * "Un usuario no se elimina si ya vendio. Se desactiva" (docs/01). Las FK hacia
 * users son nullOnDelete: antes el delete() a secas dejaba sus ventas sin
 * vendedor en silencio, y con ellas las comisiones que se calculan sobre ellas.
 */
class UserDestroyModal extends Component
{
    public $openModal = false;
    public ?User $user = null;
    public int $movimientos = 0;
    public bool $esUnoMismo = false;

    public function render()
    {
        return view('livewire.user.modals.user-destroy-modal');
    }

    #[On('openUserDestroyModal')]
    public function openModal($id)
    {
        $this->user = User::findOrFail($id);
        $this->movimientos = $this->user->cantidadMovimientos();
        $this->esUnoMismo = $this->user->id === Auth::id();
        $this->openModal = true;
    }

    public function destroy()
    {
        abort_unless(Auth::user()?->can('user.delete'), 403);

        $user = User::findOrFail($this->user->id);

        if ($user->id === Auth::id()) {
            toastr()->error('No puedes eliminar tu propio usuario.');
            return;
        }

        // Se vuelve a contar: entre abrir el modal y confirmar pudo vender.
        if ($user->cantidadMovimientos() > 0) {
            toastr()->error('El usuario tiene ventas o compras registradas: desactívalo en lugar de eliminarlo.');
            $this->movimientos = $user->cantidadMovimientos();
            return;
        }

        $user->delete();
        $this->dispatch('refreshUserTable');
        toastr()->success('Usuario eliminado exitosamente');
        $this->reset();
    }

    public function desactivar()
    {
        abort_unless(Auth::user()?->can('user.desactivar'), 403);

        $user = User::findOrFail($this->user->id);

        if ($user->id === Auth::id()) {
            toastr()->error('No puedes desactivar tu propio usuario.');
            return;
        }

        $user->desactivar();
        $this->dispatch('refreshUserTable');
        toastr()->success('Usuario desactivado: ya no puede entrar al sistema.');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }
}
