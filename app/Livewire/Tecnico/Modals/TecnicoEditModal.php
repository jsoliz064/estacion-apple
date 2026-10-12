<?php

namespace App\Livewire\Tecnico\Modals;

use App\Models\Tecnicos;
use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\On;

class TecnicoEditModal extends Component
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

    #[On('openTecnicoEditModal')]
    public function openModal($id)
    {
        $this->tecnico = Tecnicos::findOrFail($id)->toArray();
        $this->openModal = true;
    }

    public function update()
    {
        $this->tecnico['user_id'] = ($this->tecnico['user_id'] ?? null) ?: null;
        $this->validate();

        $uid = $this->tecnico['user_id'];
        if ($uid && Tecnicos::where('user_id', $uid)->whereKeyNot($this->tecnico['id'])->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['tecnico.user_id' => 'Ese usuario ya está vinculado a otro técnico.']);
        }

        $tecnico = Tecnicos::find($this->tecnico['id']);
        $tecnico->nombre = $this->tecnico['nombre'];
        $tecnico->comision_porcentaje = $this->tecnico['comision_porcentaje'];
        $tecnico->user_id = $uid;
        $tecnico->save();
        $this->dispatch('refreshTecnicoTable');
        toastr()->success('Técnico editado exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }
    public function render()
    {
        return view('livewire.tecnico.modals.tecnico-edit-modal', [
            'usuarios' => $this->openModal
                ? User::where(fn($q) => $q->where('activo', true)->orWhere('id', $this->tecnico['user_id'] ?? 0))->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }
}
