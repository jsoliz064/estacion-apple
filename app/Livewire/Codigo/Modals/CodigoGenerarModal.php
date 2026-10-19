<?php

namespace App\Livewire\Codigo\Modals;

use App\Http\Controllers\CodigoBarrasController;
use App\Services\CodigoBarrasService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/** Genera un lote de codigos de barras y abre su hoja de stickers. */
class CodigoGenerarModal extends Component
{
    public bool $openModal = false;

    public $cantidad = 10;
    public $copias = 1;
    public string $nota = '';
    public string $tamano = CodigoBarrasController::TAMANO_POR_DEFECTO;

    #[On('openCodigoGenerarModal')]
    public function openModal(?string $tamano = null): void
    {
        $this->reset();
        $this->resetErrorBag();

        if ($tamano && array_key_exists($tamano, CodigoBarrasController::TAMANOS)) {
            $this->tamano = $tamano;
        }

        $this->openModal = true;
    }

    public function generar(): void
    {
        abort_unless(Auth::user()?->can('codigo.generar'), 403);

        $this->validate([
            'cantidad' => 'required|integer|min:1|max:' . CodigoBarrasService::MAX_CODIGOS,
            'copias' => 'required|integer|min:1|max:' . CodigoBarrasService::MAX_COPIAS,
            'nota' => 'nullable|string|max:255',
            'tamano' => ['required', Rule::in(array_keys(CodigoBarrasController::TAMANOS))],
        ], [
            'cantidad.max' => 'Se generan hasta ' . CodigoBarrasService::MAX_CODIGOS . ' códigos por vez.',
            'copias.max' => 'Hasta ' . CodigoBarrasService::MAX_COPIAS . ' copias por código.',
        ]);

        $lote = DB::transaction(fn() => app(CodigoBarrasService::class)
            ->generarLote((int) $this->cantidad, (int) $this->copias, $this->nota));

        toastr()->success("Lote #{$lote->id}: {$lote->cantidad} códigos generados.");

        $this->dispatch('codigosGenerados', loteId: $lote->id, tamano: $this->tamano);
        $this->dispatch('abrir-impresion', url: route('codigos.imprimir', ['id' => $lote->id, 'tam' => $this->tamano]));
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.codigo.modals.codigo-generar-modal', [
            'tamanos' => CodigoBarrasController::opcionesTamano(),
        ]);
    }
}
