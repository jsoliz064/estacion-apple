<?php

namespace App\Livewire\Reserva\Modals;

use App\Enums\SenaDestino;
use App\Models\Reserva;
use App\Services\ReservaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

/** Cancelar una reserva: el equipo vuelve a Inventario y se elige que pasa con la seña. */
class ReservaCancelarModal extends Component
{
    public bool $openModal = false;
    public ?int $reservaId = null;
    public string $destino = '';
    public string $nota = '';

    #[On('openReservaCancelarModal')]
    public function openModal($id): void
    {
        $this->reset(['destino', 'nota']);
        $this->resetErrorBag();
        $this->reservaId = Reserva::findOrFail($id)->id;
        $this->openModal = true;
    }

    public function cancelar(): void
    {
        abort_unless(Auth::user()?->can('reserva.cancelar'), 403);

        $this->validate([
            'destino' => ['required', Rule::in(SenaDestino::values())],
            'nota' => 'nullable|string|max:200',
        ], ['destino.required' => 'Elige qué pasa con la seña.']);

        try {
            DB::transaction(fn() => app(ReservaService::class)->cancelar(
                Reserva::findOrFail($this->reservaId), SenaDestino::from($this->destino), $this->nota, Auth::user(),
            ));
        } catch (ValidationException $e) {
            toastr()->error(implode(' ', $e->validator->errors()->all()));

            return;
        }

        toastr()->success('Reserva cancelada: el equipo vuelve al inventario.');
        $this->dispatch('reservasActualizadas');
        $this->dispatch('refreshProductoTable');
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.reserva.modals.reserva-cancelar-modal', [
            'reserva' => $this->openModal && $this->reservaId ? Reserva::with(['producto.modelo', 'cliente', 'metodo'])->find($this->reservaId) : null,
        ]);
    }
}
