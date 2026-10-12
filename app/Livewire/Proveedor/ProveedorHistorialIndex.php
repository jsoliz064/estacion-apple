<?php

namespace App\Livewire\Proveedor;

use App\Models\Compra;
use App\Models\CompraReclamo;
use App\Models\Proveedor;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * La ficha del proveedor (docs/01): cuanto se le debe, sus compras, los pagos
 * y los reclamos abiertos con el.
 */
class ProveedorHistorialIndex extends Component
{
    public $proveedor;

    /** compras | pagos | reclamos */
    public string $pestana = 'compras';

    public function mount($proveedor_id)
    {
        $this->proveedor = Proveedor::findOrFail($proveedor_id);
    }

    public function verPestana(string $pestana): void
    {
        $this->pestana = in_array($pestana, ['compras', 'pagos', 'reclamos'], true) ? $pestana : 'compras';
    }

    public function pagar(): void
    {
        abort_unless(auth()->user()->can('pago-proveedor.create'), 403);

        $this->dispatch('openPagoProveedorModal', proveedorId: $this->proveedor->id);
    }

    #[On('pagosProveedorActualizados')]
    #[On('reclamosActualizados')]
    public function refrescar(): void
    {
    }

    public function render()
    {
        $fila = Compra::where('proveedor_id', $this->proveedor->id)->toBase()
            ->selectRaw('COUNT(*) as compras, COALESCE(SUM(total), 0) as total, COALESCE(SUM(CASE WHEN saldo > 0 THEN saldo ELSE 0 END), 0) as deuda, MAX(fecha) as ultima')
            ->first();

        $reclamosAbiertos = CompraReclamo::abiertos()->whereHas('compra', fn($q) => $q->where('proveedor_id', $this->proveedor->id))->count();

        return view('livewire.proveedor.proveedor-historial-index', [
            'compras' => (int) $fila->compras,
            'totalComprado' => round((float) $fila->total, 2),
            'deuda' => round((float) $fila->deuda, 2),
            'ultima' => $fila->ultima,
            'reclamosAbiertos' => $reclamosAbiertos,
            'reclamos' => $this->pestana === 'reclamos'
                ? CompraReclamo::with(['producto.modelo', 'reemplazo'])
                    ->whereHas('compra', fn($q) => $q->where('proveedor_id', $this->proveedor->id))
                    ->orderByRaw("estado = 'Abierto' DESC")->latest('id')->get()
                : collect(),
        ]);
    }
}
