<?php

namespace App\Livewire\Color\Modals;

use App\Models\Color;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

/** Crear o editar un color de equipo. */
class ColorFormModal extends Component
{
    public bool $openModal = false;
    public ?int $colorId = null;

    public string $nombre = '';
    public string $hex = '#9CA3AF';
    public $orden = 0;
    public bool $activo = true;

    /** Equipos con este color: con alguno, el nombre ya no se cambia. */
    public int $equipos = 0;

    #[On('openColorFormModal')]
    public function openModal($id = null): void
    {
        $this->reset();
        $this->resetErrorBag();

        if ($id) {
            $color = Color::findOrFail($id);
            $this->colorId = $color->id;
            $this->nombre = $color->nombre;
            $this->hex = $color->hex;
            $this->orden = $color->orden;
            $this->activo = $color->activo;
            $this->equipos = $color->equiposQueLoUsan();
        } else {
            $this->orden = (int) Color::max('orden') + 1;
        }

        $this->openModal = true;
    }

    public function guardar(): void
    {
        abort_unless(Auth::user()?->can($this->colorId ? 'producto-color.edit' : 'producto-color.create'), 403);

        $this->nombre = trim($this->nombre);
        $this->hex = strtoupper(trim($this->hex));
        $datos = $this->validate([
            'nombre' => ['required', 'string', 'max:50', Rule::unique('colores', 'nombre')->ignore($this->colorId)],
            'hex' => ['required', 'regex:/^#[0-9A-F]{6}$/'],
            'orden' => 'required|integer|min:0|max:999',
            'activo' => 'boolean',
        ], [
            'nombre.required' => 'Escribe el nombre del color.',
            'nombre.unique' => 'Ya existe un color con ese nombre.',
            'hex.required' => 'Elige la muestra del color.',
            'hex.regex' => 'La muestra debe ser un hexadecimal como #1F2937.',
        ]);

        if ($this->colorId) {
            $color = Color::findOrFail($this->colorId);

            // productos.color guarda el nombre: renombrar uno en uso dejaria
            // esos equipos con un color que ya no esta en la lista. Se cuenta
            // de nuevo: entre abrir y guardar pudo cargarse un equipo.
            if ($color->nombre !== $datos['nombre'] && ($usados = $color->equiposQueLoUsan()) > 0) {
                $this->equipos = $usados;
                throw ValidationException::withMessages([
                    'nombre' => "Hay {$usados} equipo(s) con este color: no se puede renombrar. Cree un color nuevo y desactive éste.",
                ]);
            }

            $color->update($datos);
            toastr()->success('Color actualizado.');
        } else {
            Color::create($datos);
            toastr()->success('Color creado.');
        }

        $this->dispatch('refreshColorTable');
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.color.modals.color-form-modal');
    }
}
