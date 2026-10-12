<?php

namespace App\Livewire\Venta\Modals;

use App\Models\VentaDetalle;
use App\Services\AnulacionVentaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Anular UNA linea de una venta (AnulacionVentaService::anularLinea): un equipo
 * vuelve a Inventario (descobrando antes sus repuestos de taller), un articulo
 * devuelve su stock a la sucursal de la linea, un cobro de taller se borra sin
 * tocar stock. Si era la ultima linea, la venta desaparece.
 */
class VentaDetalleDestroyModal extends Component
{
    public $openModal = false;
    public ?int $lineaId = null;

    #[On('openVentaDetalleDestroyModal')]
    public function openModal($id)
    {
        $this->lineaId = VentaDetalle::find($id)?->id;

        if (!$this->lineaId) {
            toastr()->error('Esa línea ya no existe.');

            return;
        }

        $this->openModal = true;
    }

    public function eliminarDetalle()
    {
        abort_unless(Auth::user()?->can('venta.detalle.delete'), 403);

        // Se relee de la base: entre abrir el modal y confirmar, otro pudo anularla.
        $linea = VentaDetalle::with('venta')->find($this->lineaId);

        if (!$linea) {
            toastr()->info('Esa línea ya se había anulado. No se hizo nada.');
            $this->dispatch('refreshVentaDetalleTable');
            $this->closeModal();

            return;
        }

        try {
            $ventaBorrada = DB::transaction(fn() => app(AnulacionVentaService::class)->anularLinea($linea));
        } catch (ValidationException $e) {
            // La precondicion ("el producto ya esta en X") tiene que llegar al usuario.
            toastr()->error(implode(' ', $e->validator->errors()->all()));

            return;
        }

        toastr()->success($ventaBorrada ? 'Era la última línea: la venta quedó anulada.' : 'Línea anulada.');

        if ($ventaBorrada) {
            return redirect()->route('ventas');
        }

        $this->dispatch('refreshVentaDetalleTable');
        $this->dispatch('refreshVentaDetalle');
        $this->closeModal();
    }

    public function closeModal()
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.venta.modals.venta-detalle-destroy-modal', [
            'linea' => $this->openModal && $this->lineaId
                ? VentaDetalle::with(['producto.modelo', 'repuesto', 'accesorio'])->find($this->lineaId)
                : null,
        ]);
    }
}
