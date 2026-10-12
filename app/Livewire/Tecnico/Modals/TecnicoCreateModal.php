<?php

namespace App\Livewire\Tecnico\Modals;

use App\Models\Tecnicos;
use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\On;


class TecnicoCreateModal extends Component
{
    public $openModal = false;
    public $tecnico = [];

    protected $rules = [
        'tecnico.nombre' => 'required|string|max:255',
        'tecnico.comision_porcentaje' => 'required|numeric|min:0|max:100',
        'tecnico.user_id' => 'nullable|integer|exists:users,id',
    ];

    protected $messages = [
        'tecnico.nombre' => 'Debe ingresar un nombre',
        'tecnico.comision_porcentaje.required' => 'Debe ingresar el porcentaje de comisión',
        'tecnico.comision_porcentaje.numeric' => 'La comisión debe ser un número',
        'tecnico.comision_porcentaje.min' => 'La comisión no puede ser negativa',
        'tecnico.comision_porcentaje.max' => 'La comisión no puede pasar de 100',
        'tecnico.user_id.exists' => 'Ese usuario no existe',
    ];

    #[On('openTecnicoCreateModal')]
    public function openModal()
    {
        $this->openModal = true;
        // 50/50 de la mano de obra: el acuerdo por defecto del negocio.
        $this->tecnico['comision_porcentaje'] = 50;
    }

    public function store()
    {
        $this->tecnico['user_id'] = ($this->tecnico['user_id'] ?? null) ?: null;
        $this->validate();
        $this->exigirUsuarioLibre();
        Tecnicos::create($this->tecnico);
        $this->dispatch('refreshTecnicoTable');
        toastr()->success('Técnico creado exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }

    /** Un usuario es de un solo tecnico (UNIQUE tecnicos.user_id). */
    private function exigirUsuarioLibre(): void
    {
        $uid = $this->tecnico['user_id'] ?? null;
        if ($uid && Tecnicos::where('user_id', $uid)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['tecnico.user_id' => 'Ese usuario ya está vinculado a otro técnico.']);
        }
    }

    public function render()
    {
        return view('livewire.tecnico.modals.tecnico-create-modal', [
            'usuarios' => $this->openModal ? User::where('activo', true)->orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }
}
