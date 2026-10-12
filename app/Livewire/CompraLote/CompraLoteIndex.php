<?php

namespace App\Livewire\CompraLote;

use App\Models\Compra;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * El detalle de una compra: su cabecera, el estado y lo pagado al proveedor,
 * los equipos (que se dan de alta aqui, uno por uno, con IMEI y fotos), los
 * reclamos y los repuestos y accesorios (CompraArticulos). Todo se guarda al
 * momento; en borrador, «Finalizar compra» mete el stock y libera los equipos.
 */
class CompraLoteIndex extends Component
{
    #[Locked]
    public int $compraId;

    public function mount($compra)
    {
        $this->compraId = $compra->id;
    }

    public function openProductoEstadoMasivoModal()
    {
        $this->dispatch('openProductoEstadoMasivoModal', compraId: $this->compraId);
    }

    public function finalizar(): void
    {
        abort_unless(auth()->user()->can('compra.finalizar'), 403);

        $this->dispatch('openCompraFinalizarModal', compraId: $this->compraId);
    }

    public function registrarPago(): void
    {
        abort_unless(auth()->user()->can('pago-proveedor.create'), 403);

        $compra = Compra::find($this->compraId);
        $this->dispatch('openPagoProveedorModal', proveedorId: $compra->proveedor_id, compraId: $compra->id);
    }

    public function anularPago($pagoId): void
    {
        $this->dispatch('openPagoProveedorAnularModal', (int) $pagoId);
    }

    public function cerrarReclamo($reclamoId): void
    {
        abort_unless(auth()->user()->can('compra.reclamo'), 403);

        $this->dispatch('openReclamoCerrarModal', (int) $reclamoId);
    }

    #[On('refreshCompraDetalle')]
    #[On('pagosProveedorActualizados')]
    #[On('reclamosActualizados')]
    public function refrescar(): void
    {
        // El render relee la compra: el total cambia al agregar o quitar equipos.
    }

    public function render()
    {
        $compra = Compra::with([
            'proveedor', 'sucursal', 'user', 'detalles.repuesto', 'detalles.accesorio',
            'pagos' => fn($q) => $q->with(['metodo', 'user'])->orderBy('id'),
            'reclamos' => fn($q) => $q->with(['producto.modelo', 'reemplazo'])->orderByRaw("estado = 'Abierto' DESC")->orderBy('id'),
        ])->findOrFail($this->compraId);

        return view('livewire.compra-lote.compra-lote-index', [
            'compra' => $compra,
            'articulos' => $compra->detalles->whereNull('producto_id')->values(),
            'equipos' => $compra->detalles->whereNotNull('producto_id')->count(),
        ]);
    }
}
