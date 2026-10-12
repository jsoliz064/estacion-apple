<?php

namespace App\Livewire\Comision\Modals;

use App\Models\ComisionLiquidacion;
use App\Models\User;
use App\Services\ComisionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * El detalle de una liquidacion (quien cobro, cuanto, cuando y por que), con
 * Anular para quien tenga comision.anular. Desde «Mis comisiones» solo abre
 * las propias.
 */
class LiquidacionVerModal extends Component
{
    public bool $openModal = false;

    #[Locked]
    public ?int $liquidacionId = null;

    public bool $confirmarAnular = false;

    #[On('openLiquidacionVerModal')]
    public function openModal(int $id): void
    {
        $liquidacion = ComisionLiquidacion::findOrFail($id);
        abort_unless($this->puedeVer($liquidacion, Auth::user()), 403);

        $this->liquidacionId = $liquidacion->id;
        $this->confirmarAnular = false;
        $this->resetErrorBag();
        $this->openModal = true;
    }

    private function puedeVer(ComisionLiquidacion $liquidacion, User $user): bool
    {
        if ($user->can('comision.index')) {
            return true;
        }

        return $user->can('comision.propias')
            && ((int) $liquidacion->user_id === $user->id
                || ($liquidacion->tecnico_id && (int) $user->tecnico?->id === (int) $liquidacion->tecnico_id));
    }

    public function anular(): void
    {
        abort_unless(Auth::user()?->can('comision.anular'), 403);

        $liquidacion = ComisionLiquidacion::find($this->liquidacionId);

        if (!$liquidacion) {
            toastr()->info('Esa liquidación ya se había anulado.');
            $this->despues();

            return;
        }

        DB::transaction(fn() => app(ComisionService::class)->anularLiquidacion($liquidacion));

        toastr()->success('Liquidación #' . $this->liquidacionId . ' anulada: sus comisiones vuelven a estar por pagar.');
        $this->despues();
    }

    private function despues(): void
    {
        $this->dispatch('comisionesActualizadas');
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->openModal = false;
        $this->reset(['liquidacionId', 'confirmarAnular']);
    }

    public function render()
    {
        $liquidacion = $this->openModal && $this->liquidacionId
            ? ComisionLiquidacion::with(['user:id,name', 'tecnico:id,nombre', 'pagadoPor:id,name', 'comisiones' => fn($q) => $q->orderBy('ganada_at')->orderBy('id')])
                ->find($this->liquidacionId)
            : null;

        return view('livewire.comision.modals.liquidacion-ver-modal', ['liquidacion' => $liquidacion]);
    }
}
