<?php

namespace App\Livewire\Sucursal\Modals;

use App\Models\Sucursal;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class SucursalCreateModal extends Component
{
    public $openModal = false;
    public $sucursal = [];

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

    #[On('openSucursalCreateModal')]
    public function openModal()
    {
        $this->reset();
        $this->sucursal['activa'] = true;
        $this->openModal = true;
    }

    public function store()
    {
        // El @can del blade solo esconde el boton; esta es la capa que protege.
        abort_unless(Auth::user()?->can('sucursal.create'), 403);

        $this->validate();
        Sucursal::create($this->sucursal);
        $this->dispatch('refreshSucursalTable');
        toastr()->success('Sucursal creada exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.sucursal.modals.sucursal-create-modal');
    }
}
