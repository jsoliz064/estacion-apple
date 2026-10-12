<?php

namespace App\Livewire\Compra\Modals;

use App\Enums\ProductoColor;
use App\Enums\ReclamoResolucion;
use App\Models\CompraReclamo;
use App\Services\ReclamoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Cerrar un reclamo (ReclamoService::cerrar): Reemplazo (el equipo nuevo, con su
 * IMEI, entra en la misma compra con el costo del fallado), Descuento (el
 * fallado vuelve y su costo sale de la compra) o Aceptado (vuelve a
 * Inventario o a Roto).
 */
class ReclamoCerrarModal extends Component
{
    public bool $openModal = false;
    public ?int $reclamoId = null;

    public string $resolucion = '';
    public string $imei = '';
    public $bateria_porcentaje = 100;
    public string $color = '';
    public string $destino = 'Inventario';
    public string $nota = '';

    #[On('openReclamoCerrarModal')]
    public function openModal($id): void
    {
        $this->reset(['resolucion', 'imei', 'bateria_porcentaje', 'color', 'destino', 'nota']);
        $this->resetErrorBag();
        $this->reclamoId = CompraReclamo::findOrFail($id)->id;
        $this->openModal = true;
    }

    public function cerrar(): void
    {
        abort_unless(Auth::user()?->can('compra.reclamo'), 403);

        $this->validate([
            'resolucion' => ['required', Rule::in(ReclamoResolucion::values())],
            'destino' => 'required_if:resolucion,Aceptado|in:Inventario,Roto',
            'nota' => 'nullable|string|max:255',
        ], ['resolucion.required' => 'Elige cómo se cierra el reclamo.']);

        DB::transaction(fn() => app(ReclamoService::class)->cerrar(
            CompraReclamo::findOrFail($this->reclamoId),
            ReclamoResolucion::from($this->resolucion),
            [
                'imei' => $this->imei,
                'bateria_porcentaje' => $this->bateria_porcentaje,
                'color' => $this->color,
                'destino' => $this->destino,
                'nota' => $this->nota,
            ],
            Auth::user(),
        ));

        toastr()->success('Reclamo cerrado.');
        $this->dispatch('reclamosActualizados');
        $this->dispatch('refreshProductoTable');
        $this->dispatch('refreshCompraDetalle');
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.compra.modals.reclamo-cerrar-modal', [
            'reclamo' => $this->openModal && $this->reclamoId ? CompraReclamo::with(['producto.modelo', 'compra.proveedor'])->find($this->reclamoId) : null,
            'colores' => ProductoColor::cases(),
        ]);
    }
}
