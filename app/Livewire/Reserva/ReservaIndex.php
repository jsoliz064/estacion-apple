<?php

namespace App\Livewire\Reserva;

use App\Models\Reserva;
use Livewire\Attributes\On;
use Livewire\Component;

/** La pantalla de reservas: lo apartado con seña, para concretarlo o cancelarlo. */
class ReservaIndex extends Component
{
    public function nuevaReserva(): void
    {
        abort_unless(auth()->user()->can('reserva.create'), 403);

        $this->dispatch('openReservaCreateModal');
    }

    #[On('reservasActualizadas')]
    public function refrescar(): void
    {
    }

    public function render()
    {
        $resumen = Reserva::activas()->toBase()
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(sena), 0) as senas')
            ->first();

        return view('livewire.reserva.reserva-index', ['resumen' => $resumen]);
    }
}
