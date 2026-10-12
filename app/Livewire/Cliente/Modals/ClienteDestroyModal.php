<?php

namespace App\Livewire\Cliente\Modals;

use App\Models\Cliente;
use Illuminate\Database\QueryException;
use Livewire\Attributes\On;
use Livewire\Component;

class ClienteDestroyModal extends Component
{
    public $openModal = false;
    public $cliente;

    /** Cuantas ordenes tiene, para avisar antes de intentar borrar. */
    public int $ordenes = 0;

    #[On('openClienteDestroyModal')]
    public function openModal($id)
    {
        $this->cliente = Cliente::findOrFail($id);
        $this->ordenes = $this->cliente->cantidadOrdenes();
        $this->openModal = true;
    }

    public function destroy()
    {
        try {
            $this->cliente->delete();
            $this->dispatch('refreshClienteTable');
            toastr()->success('Cliente eliminado exitosamente');
            $this->reset();
        } catch (QueryException $e) {
            // La FK de ventas.cliente_id va en restrict a proposito: con nullOnDelete, borrar un cliente dejaria
            // sus ventas enlazadas a nada en silencio y su historial
            // desapareceria sin aviso. 1451 = fila padre referenciada.
            if (($e->errorInfo[1] ?? null) === 1451) {
                toastr()->error(
                    'No se puede eliminar: el cliente tiene ' . $this->ordenes
                        . ' orden(es) registradas. Sus ventas se quedarian sin dueno.'
                );

                return;
            }

            throw $e;
        }
    }

    public function closeModal()
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.cliente.modals.cliente-destroy-modal');
    }
}
