<?php

namespace App\Livewire\Producto\Modals;

use App\Enums\BajaMotivo;
use App\Models\Producto;
use App\Services\BajaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Dar de baja un equipo (o revertir la baja). La baja ARCHIVA el equipo sin
 * tocar su estado: Fuera (salio del local) y Roto (esta roto) son estados, no
 * bajas. Un equipo dado de baja deja de verse en tablas, ventas y catalogo.
 *
 * Las reglas viven en BajaService; el modal solo pide el motivo.
 */
class ProductoBajaModal extends Component
{
    public $openModal = false;

    #[Locked]
    public ?int $productoId = null;

    public $motivo = '';
    public $nota = '';

    protected $messages = [
        'motivo.required' => 'Elige el motivo de la baja.',
    ];

    #[On('openProductoBajaModal')]
    public function openModal($id): void
    {
        $this->reset(['motivo', 'nota']);
        $this->resetValidation();
        $this->productoId = (int) $id;
        $this->openModal = true;
    }

    public function darDeBaja(): void
    {
        abort_unless(Auth::user()->can('producto.baja'), 403);

        $this->validate([
            'motivo' => ['required', Rule::in(BajaMotivo::valoresManuales())],
            'nota' => 'nullable|string|max:255',
        ]);

        DB::transaction(fn() => app(BajaService::class)->darDeBajaProducto(
            $this->productoId,
            BajaMotivo::from($this->motivo),
            $this->nota,
        ));

        $this->terminar('Equipo dado de baja: ya no aparece en el inventario.');
    }

    public function revertir(): void
    {
        abort_unless(Auth::user()->can('producto.baja'), 403);

        DB::transaction(fn() => app(BajaService::class)->revertirBajaProducto($this->productoId));

        $this->terminar('Baja revertida: el equipo vuelve al inventario.');
    }

    private function terminar(string $mensaje): void
    {
        $this->dispatch('refreshProductoTable');
        toastr()->success($mensaje);
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->reset();
    }

    public function render()
    {
        $producto = $this->openModal && $this->productoId
            ? Producto::with(['modelo', 'bajaUser'])->find($this->productoId)
            : null;

        return view('livewire.producto.modals.producto-baja-modal', ['producto' => $producto]);
    }
}
