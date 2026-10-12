<?php

namespace App\Livewire\Comision;

use App\Models\Comision;
use App\Models\ComisionLiquidacion;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * «Mis comisiones»: lo del usuario conectado, sin acciones. Sus ventas y, si es
 * tecnico (tecnicos.user_id), sus reparaciones.
 */
class MisComisionesIndex extends Component
{
    #[On('comisionesActualizadas')]
    public function refrescar(): void
    {
    }

    public function verLiquidacion(int $id): void
    {
        $this->dispatch('openLiquidacionVerModal', $id);
    }

    public function render()
    {
        $user = Auth::user();
        $tecnico = $user->tecnico;

        $liquidaciones = ComisionLiquidacion::with(['user:id,name', 'tecnico:id,nombre', 'pagadoPor:id,name'])
            ->where(fn($q) => $q->where('user_id', $user->id)
                ->when($tecnico, fn($w) => $w->orWhere('tecnico_id', $tecnico->id)))
            ->latest('id')
            ->limit(50)
            ->get();

        return view('livewire.comision.mis-comisiones-index', [
            'cifras' => Comision::cifras(Comision::query()->deUsuario($user)),
            'tecnico' => $tecnico,
            'liquidaciones' => $liquidaciones,
        ]);
    }
}
