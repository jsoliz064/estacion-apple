<?php

namespace App\Livewire\Color\Modals;

use App\Models\Color;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Eliminar un color, o desactivarlo si algun equipo lo lleva: productos.color
 * guarda el nombre, y borrarlo dejaria esos equipos con un color fuera de la
 * lista. Sus marcas por modelo caen en cascada.
 */
class ColorDestroyModal extends Component
{
    public bool $openModal = false;
    public ?int $colorId = null;
    public int $equipos = 0;

    #[On('openColorDestroyModal')]
    public function openModal($id): void
    {
        $color = Color::findOrFail($id);
        $this->colorId = $color->id;
        $this->equipos = $color->equiposQueLoUsan();
        $this->openModal = true;
    }

    public function destroy(): void
    {
        abort_unless(Auth::user()?->can('producto-color.delete'), 403);

        $color = Color::findOrFail($this->colorId);

        // Se vuelve a contar: entre abrir y confirmar pudo cargarse un equipo.
        if (($this->equipos = $color->equiposQueLoUsan()) > 0) {
            toastr()->error('El color lo usan equipos: desactívalo en lugar de eliminarlo.');

            return;
        }

        $color->delete();
        $this->dispatch('refreshColorTable');
        toastr()->success('Color eliminado.');
        $this->closeModal();
    }

    public function desactivar(): void
    {
        abort_unless(Auth::user()?->can('producto-color.delete'), 403);

        Color::findOrFail($this->colorId)->update(['activo' => false]);
        $this->dispatch('refreshColorTable');
        toastr()->success('Color desactivado: ya no se ofrece al cargar equipos.');
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.color.modals.color-destroy-modal', [
            'color' => $this->openModal && $this->colorId ? Color::find($this->colorId) : null,
        ]);
    }
}
