<?php

namespace App\Livewire\Cobranza\Modals;

use App\Models\VentaPago;
use App\Services\PagoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

/** Anular un pago: la venta recupera ese saldo (y vuelve a credito). PagoService::anular. */
class PagoAnularModal extends Component
{
    public bool $openModal = false;
    public ?int $pagoId = null;

    #[On('openPagoAnularModal')]
    public function openModal($id): void
    {
        $this->pagoId = VentaPago::findOrFail($id)->id;
        $this->openModal = true;
    }

    public function anular(): void
    {
        abort_unless(Auth::user()?->can('pago.anular'), 403);

        $pago = VentaPago::find($this->pagoId);

        if (!$pago) {
            toastr()->info('Ese pago ya se había anulado.');
            $this->despues();

            return;
        }

        try {
            DB::transaction(fn() => app(PagoService::class)->anular($pago, Auth::user()));
        } catch (ValidationException $e) {
            toastr()->error(implode(' ', $e->validator->errors()->all()));

            return;
        }

        toastr()->success('Pago anulado: la venta vuelve a tener ese saldo.');
        $this->despues();
    }

    private function despues(): void
    {
        $this->dispatch('pagosActualizados');
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.cobranza.modals.pago-anular-modal', [
            'pago' => $this->openModal && $this->pagoId ? VentaPago::with(['metodo', 'venta', 'user'])->find($this->pagoId) : null,
        ]);
    }
}
