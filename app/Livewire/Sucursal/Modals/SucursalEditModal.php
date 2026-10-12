<?php

namespace App\Livewire\Sucursal\Modals;

use App\Models\Sucursal;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class SucursalEditModal extends Component
{
    public $openModal = false;
    public $sucursal = [];

    /** El Almacen no se renombra ni se desactiva: el codigo lo busca por nombre. */
    public bool $esAlmacen = false;

    protected function rules(): array
    {
        return [
            'sucursal.nombre' => 'required|string|max:255',
            'sucursal.direccion' => 'nullable|string|max:255',
            'sucursal.telefono' => 'nullable|string|max:30',
            'sucursal.activa' => 'boolean',
        ];
    }

    protected function messages(): array
    {
        return [
            'sucursal.nombre.required' => 'Debe ingresar el nombre de la sucursal',
            'sucursal.telefono.max' => 'El teléfono no puede tener más de 30 caracteres',
        ];
    }

    #[On('openSucursalEditModal')]
    public function openModal($id)
    {
        $sucursal = Sucursal::findOrFail($id);
        $this->sucursal = $sucursal->only(['id', 'nombre', 'direccion', 'telefono', 'activa']);
        $this->esAlmacen = $sucursal->esAlmacen();
        $this->openModal = true;
    }

    public function update()
    {
        abort_unless(Auth::user()?->can('sucursal.edit'), 403);

        $this->validate();

        $sucursal = Sucursal::findOrFail($this->sucursal['id']);

        // En el servidor y no solo con el input deshabilitado: un nombre
        // distinto dejaria a Sucursal::almacenId() devolviendo null, y la
        // proxima reparacion terminada no tendria a donde mudarse.
        if ($sucursal->esAlmacen()) {
            $this->sucursal['nombre'] = Sucursal::ALMACEN;
            $this->sucursal['activa'] = true;
        }

        $sucursal->nombre = $this->sucursal['nombre'];
        $sucursal->direccion = $this->sucursal['direccion'] ?? null;
        $sucursal->telefono = $this->sucursal['telefono'] ?? null;
        $sucursal->activa = (bool) ($this->sucursal['activa'] ?? false);
        $sucursal->save();

        $this->dispatch('refreshSucursalTable');
        toastr()->success('Sucursal actualizada exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.sucursal.modals.sucursal-edit-modal');
    }
}
