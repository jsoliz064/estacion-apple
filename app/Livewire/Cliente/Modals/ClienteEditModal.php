<?php

namespace App\Livewire\Cliente\Modals;

use App\Models\Cliente;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class ClienteEditModal extends Component
{
    public $openModal = false;
    public $cliente = [];

    protected function rules(): array
    {
        return [
            'cliente.nombre' => 'required|string|max:255',
            // ignore() del propio id: sin el, reguardar la ficha sin tocarle el CI
            // choca consigo misma.
            'cliente.ci' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('clientes', 'ci')->ignore($this->cliente['id'] ?? null),
            ],
            'cliente.telefono' => 'nullable|string|max:30',
            'cliente.correo' => 'nullable|email|max:255',
        ];
    }

    protected function messages(): array
    {
        return [
            'cliente.nombre.required' => 'Debe ingresar el nombre del cliente',
            'cliente.ci.unique' => 'Ya existe otro cliente con ese CI',
            'cliente.correo.email' => 'El correo no tiene un formato valido',
        ];
    }

    #[On('openClienteEditModal')]
    public function openModal($id)
    {
        $this->cliente = Cliente::findOrFail($id)->toArray();
        $this->openModal = true;
    }

    public function update()
    {
        $this->validate();

        $cliente = Cliente::findOrFail($this->cliente['id']);

        try {
            $cliente->update($this->cliente);
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062 && str_contains($e->getMessage(), 'clientes_ci_unico')) {
                $this->addError('cliente.ci', 'Ya existe otro cliente con ese CI.');

                return;
            }

            throw $e;
        }

        $this->dispatch('refreshClienteTable');

        // Renombrar aqui cambia el nombre que se ve en TODAS sus ventas, porque
        // Venta::nombreCliente() lee la ficha primero. El texto congelado de cada
        // documento no se toca: es el archivo de lo que se escribio entonces.
        toastr()->success('Cliente editado exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.cliente.modals.cliente-edit-modal');
    }
}
