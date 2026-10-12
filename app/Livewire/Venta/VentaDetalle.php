<?php

namespace App\Livewire\Venta;

use App\Models\Venta;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/** La pagina de detalle de una venta: cabecera, totales, cobro y sus lineas. */
class VentaDetalle extends Component
{
    #[Locked]
    public int $ventaId;

    public function mount($venta)
    {
        $this->ventaId = $venta->id;
    }

    public function render()
    {
        // La carga va en render(): Livewire rehidrata por id y sin relaciones.
        $venta = Venta::with(['user', 'sucursal', 'fichaCliente', 'comision', 'pagos' => fn($q) => $q->with(['metodo', 'user', 'producto.modelo'])->orderBy('id')])
            ->withCount('detalles')->find($this->ventaId);

        if (!$venta) {
            // Anularon la ultima linea: la venta ya no existe.
            return view('livewire.venta.venta-anulada');
        }

        return view('livewire.venta.venta-detalle', ['venta' => $venta]);
    }

    public function ventaEditar()
    {
        return redirect()->route('ventas.editar', $this->ventaId);
    }

    public function anularVenta()
    {
        $this->dispatch('openVentaDestroyModal', $this->ventaId);
    }

    public function cobrar(): void
    {
        $venta = Venta::find($this->ventaId);

        if ($venta?->cliente_id) {
            $this->dispatch('openCobroModal', clienteId: $venta->cliente_id, ventaId: $venta->id);
        }
    }

    public function anularPago($pagoId): void
    {
        $this->dispatch('openPagoAnularModal', $pagoId);
    }

    #[On('refreshVentaDetalle')]
    #[On('pagosActualizados')]
    public function refreshVentaDetalle()
    {
    }
}
