<?php

namespace App\Livewire\Venta\Modals;

use App\Models\Venta;
use App\Services\AnulacionVentaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

/** Anular una venta entera: todas sus lineas (AnulacionVentaService::anularVenta) y la cabecera. */
class VentaDestroyModal extends Component
{
    public $openModal = false;
    public ?int $ventaId = null;

    #[On('openVentaDestroyModal')]
    public function openModal($id)
    {
        $this->ventaId = Venta::findOrFail($id)->id;
        $this->openModal = true;
    }

    public function anular()
    {
        abort_unless(Auth::user()?->can('venta.delete'), 403);

        try {
            DB::transaction(fn() => app(AnulacionVentaService::class)->anularVenta(Venta::lockForUpdate()->findOrFail($this->ventaId)));
        } catch (ValidationException $e) {
            toastr()->error(implode(' ', $e->validator->errors()->all()));

            return;
        }

        toastr()->success('Venta anulada: los equipos volvieron al inventario y el stock a su sucursal.');

        return redirect()->route('ventas');
    }

    public function closeModal()
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.venta.modals.venta-destroy-modal', [
            // Los regalos no cuentan como lineas: se van con su equipo.
            'venta' => $this->openModal && $this->ventaId
                ? Venta::withCount([
                    'detalles' => fn($q) => $q->whereNull('producto_regalo_id'),
                    'detalles as regalos_count' => fn($q) => $q->whereNotNull('producto_regalo_id'),
                ])->with('comision.user')->find($this->ventaId)
                : null,
        ]);
    }
}
