<?php

namespace App\Livewire\Compra\Modals;

use App\Models\Compra;
use App\Services\CompraService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Eliminar una compra. En borrador se va entera, equipos incluidos (nunca se
 * vendieron ni entro su stock). Finalizada, solo sin equipos (cada equipo se
 * quita desde el detalle, con sus reglas) y sus repuestos y accesorios salen
 * del stock de la sucursal de cada linea; si ya se vendieron,
 * CompraService::eliminar() falla con mensaje y no se borra nada.
 */
class CompraDestroyModal extends Component
{
    public $openModal = false;
    public ?int $compraId = null;

    #[On('openCompraDestroyModal')]
    public function openModal($id)
    {
        $this->compraId = Compra::findOrFail($id)->id;
        $this->openModal = true;
    }

    public function destroy()
    {
        abort_unless(Auth::user()?->can('compra.delete'), 403);

        DB::transaction(fn() => app(CompraService::class)->eliminar(Compra::lockForUpdate()->findOrFail($this->compraId)));

        $this->dispatch('refreshCompraTable');
        toastr()->success('Compra eliminada.');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }

    public function render()
    {
        $compra = $this->openModal && $this->compraId
            ? Compra::with(['proveedor', 'detalles.repuesto', 'detalles.accesorio'])->find($this->compraId)
            : null;

        return view('livewire.compra.modals.compra-destroy-modal', [
            'compra' => $compra,
            'equipos' => $compra ? $compra->detalles->whereNotNull('producto_id')->count() : 0,
            'borrador' => $compra?->esBorrador() ?? false,
        ]);
    }
}
