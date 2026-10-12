<?php

namespace App\Livewire\MetodoPago\Modals;

use App\Models\MetodoPago;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/** Crear o editar un metodo de pago. */
class MetodoPagoFormModal extends Component
{
    public bool $openModal = false;
    public ?int $metodoId = null;

    public string $nombre = '';
    public $orden = 0;
    public bool $activo = true;

    #[On('openMetodoPagoFormModal')]
    public function openModal($id = null): void
    {
        $this->reset();
        $this->resetErrorBag();

        if ($id) {
            $metodo = MetodoPago::findOrFail($id);
            $this->metodoId = $metodo->id;
            $this->nombre = $metodo->nombre;
            $this->orden = $metodo->orden;
            $this->activo = $metodo->activo;
        } else {
            $this->orden = (int) MetodoPago::max('orden') + 1;
        }

        $this->openModal = true;
    }

    public function guardar(): void
    {
        abort_unless(Auth::user()?->can($this->metodoId ? 'metodo-pago.edit' : 'metodo-pago.create'), 403);

        $this->nombre = trim($this->nombre);
        $datos = $this->validate([
            'nombre' => ['required', 'string', 'max:60', Rule::unique('metodos_pago', 'nombre')->ignore($this->metodoId)],
            'orden' => 'required|integer|min:0|max:999',
            'activo' => 'boolean',
        ], [
            'nombre.required' => 'Escribe el nombre del método.',
            'nombre.unique' => 'Ya existe un método con ese nombre.',
        ]);

        if ($this->metodoId) {
            MetodoPago::findOrFail($this->metodoId)->update($datos);
            toastr()->success('Método de pago actualizado.');
        } else {
            MetodoPago::create($datos);
            toastr()->success('Método de pago creado.');
        }

        $this->dispatch('refreshMetodoPagoTable');
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.metodo-pago.modals.metodo-pago-form-modal');
    }
}
