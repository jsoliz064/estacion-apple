<?php

namespace App\Livewire\Compra\Modals;

use App\Models\Compra;
use App\Models\Producto;
use App\Services\ReclamoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

/** Marcar un equipo de la compra como fallado y reclamarlo al proveedor (ReclamoService::abrir). */
class ReclamoAbrirModal extends Component
{
    public bool $openModal = false;
    public ?int $productoId = null;
    public string $motivo = '';

    #[On('openReclamoAbrirModal')]
    public function openModal($productoId): void
    {
        $this->reset(['motivo']);
        $this->resetErrorBag();
        $this->productoId = Producto::findOrFail($productoId)->id;
        $this->openModal = true;
    }

    public function guardar(): void
    {
        abort_unless(Auth::user()?->can('compra.reclamo'), 403);

        $this->validate(['motivo' => 'required|string|max:255'], ['motivo.required' => 'Escribe qué tiene el equipo.']);

        $producto = Producto::with('compraDetalle')->findOrFail($this->productoId);

        try {
            DB::transaction(fn() => app(ReclamoService::class)->abrir(
                Compra::findOrFail($producto->compraDetalle?->compra_id), $producto->id, $this->motivo, Auth::user(),
            ));
        } catch (ValidationException $e) {
            toastr()->error(implode(' ', $e->validator->errors()->all()));

            return;
        }

        toastr()->success('Equipo en reclamo: ya no se puede vender ni reparar.');
        $this->dispatch('reclamosActualizados');
        $this->dispatch('refreshProductoTable');
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.compra.modals.reclamo-abrir-modal', [
            'producto' => $this->openModal && $this->productoId ? Producto::with('modelo')->find($this->productoId) : null,
        ]);
    }
}
