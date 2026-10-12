<?php

namespace App\Livewire\MetodoPago\Modals;

use App\Models\MetodoPago;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Eliminar un metodo de pago, o desactivarlo si ya tiene pagos. La FK de
 * ventas_pagos va en RESTRICT: se cuenta antes para avisar con claridad.
 */
class MetodoPagoDestroyModal extends Component
{
    public bool $openModal = false;
    public ?int $metodoId = null;
    public int $pagos = 0;

    #[On('openMetodoPagoDestroyModal')]
    public function openModal($id): void
    {
        $metodo = MetodoPago::findOrFail($id);
        $this->metodoId = $metodo->id;
        $this->pagos = $metodo->pagos()->count();
        $this->openModal = true;
    }

    public function destroy(): void
    {
        abort_unless(Auth::user()?->can('metodo-pago.delete'), 403);

        $metodo = MetodoPago::findOrFail($this->metodoId);

        // Se vuelve a contar: entre abrir y confirmar pudo entrar un cobro.
        if ($metodo->pagos()->exists()) {
            $this->pagos = $metodo->pagos()->count();
            toastr()->error('El método tiene pagos: desactívalo en lugar de eliminarlo.');

            return;
        }

        $metodo->delete();
        $this->dispatch('refreshMetodoPagoTable');
        toastr()->success('Método de pago eliminado.');
        $this->closeModal();
    }

    public function desactivar(): void
    {
        abort_unless(Auth::user()?->can('metodo-pago.delete'), 403);

        MetodoPago::findOrFail($this->metodoId)->update(['activo' => false]);
        $this->dispatch('refreshMetodoPagoTable');
        toastr()->success('Método desactivado: ya no se ofrece al cobrar.');
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.metodo-pago.modals.metodo-pago-destroy-modal', [
            'metodo' => $this->openModal && $this->metodoId ? MetodoPago::find($this->metodoId) : null,
        ]);
    }
}
