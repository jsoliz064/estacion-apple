<?php

namespace App\Livewire\ProductoModelo\Modals;

use App\Models\Color;
use App\Models\ProductoModelo;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Los colores de un modelo: los que el alta del equipo ofrece al elegirlo
 * (Color::paraModelo). Sin ninguno marcado, se ofrecen todos los activos.
 */
class ProductoModeloColoresModal extends Component
{
    public bool $openModal = false;
    public ?int $modeloId = null;
    /** @var array<int,string> ids marcados (strings: asi los devuelve el checkbox). */
    public array $marcados = [];

    #[On('openProductoModeloColoresModal')]
    public function openModal($id): void
    {
        $modelo = ProductoModelo::findOrFail($id);
        $this->resetErrorBag();
        $this->modeloId = $modelo->id;
        $this->marcados = $modelo->colores()->pluck('colores.id')->map(fn($v) => (string) $v)->all();
        $this->openModal = true;
    }

    public function guardar(): void
    {
        abort_unless(Auth::user()?->can('producto-modelo.colores'), 403);

        $this->validate([
            'marcados' => 'array',
            'marcados.*' => 'integer|exists:colores,id',
        ]);

        ProductoModelo::findOrFail($this->modeloId)->colores()->sync(array_map('intval', $this->marcados));

        toastr()->success('Colores del modelo actualizados.');
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->reset();
    }

    public function render()
    {
        $abierto = $this->openModal && $this->modeloId;

        return view('livewire.producto-modelo.modals.producto-modelo-colores-modal', [
            'modelo' => $abierto ? ProductoModelo::find($this->modeloId) : null,
            // Los activos, mas los inactivos que el modelo aun tiene marcados
            // (para poder desmarcarlos).
            'colores' => $abierto
                ? Color::query()
                    ->where(fn($q) => $q->where('activo', true)->orWhereIn('id', $this->marcados))
                    ->ordenados()
                    ->get()
                : collect(),
        ]);
    }
}
