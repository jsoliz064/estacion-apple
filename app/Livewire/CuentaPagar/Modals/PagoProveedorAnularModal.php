<?php

namespace App\Livewire\CuentaPagar\Modals;

use App\Models\CompraPago;
use App\Services\PagoProveedorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

/** Anular un pago al proveedor: la compra recupera ese saldo. PagoProveedorService::anular. */
class PagoProveedorAnularModal extends Component
{
    public bool $openModal = false;
    public ?int $pagoId = null;

    #[On('openPagoProveedorAnularModal')]
    public function openModal($id): void
    {
        $this->pagoId = CompraPago::findOrFail($id)->id;
        $this->openModal = true;
    }

    public function anular(): void
    {
        abort_unless(Auth::user()?->can('pago-proveedor.anular'), 403);

        $pago = CompraPago::find($this->pagoId);

        if (!$pago) {
            toastr()->info('Ese pago ya se había anulado.');
            $this->despues();

            return;
        }

        try {
            DB::transaction(fn() => app(PagoProveedorService::class)->anular($pago, Auth::user()));
        } catch (ValidationException $e) {
            toastr()->error(implode(' ', $e->validator->errors()->all()));

            return;
        }

        toastr()->success('Pago anulado: la compra vuelve a tener ese saldo.');
        $this->despues();
    }

    private function despues(): void
    {
        $this->dispatch('pagosProveedorActualizados');
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.cuenta-pagar.modals.pago-proveedor-anular-modal', [
            'pago' => $this->openModal && $this->pagoId ? CompraPago::with(['metodo', 'compra.proveedor', 'user'])->find($this->pagoId) : null,
        ]);
    }
}
