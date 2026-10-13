<?php

namespace App\Livewire\Codigo;

use App\Http\Controllers\CodigoBarrasController;
use App\Models\CodigoBarras;
use App\Models\CodigoLote;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Los lotes de codigos de barras generados: cuantos ya estan asignados y a
 * quien, y los botones para imprimirlos (todos o solo los libres).
 */
class CodigoIndex extends Component
{
    use WithPagination;

    /** El lote desplegado (sus codigos, uno por fila). */
    public ?int $abierto = null;

    /** El tamaño de etiqueta con que se imprime (de CodigoBarrasController::TAMANOS). */
    public string $tamano = CodigoBarrasController::TAMANO_POR_DEFECTO;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('codigo.index'), 403);
    }

    public function openCodigoGenerarModal(): void
    {
        $this->dispatch('openCodigoGenerarModal', $this->tamano);
    }

    public function alternar(int $loteId): void
    {
        $this->abierto = $this->abierto === $loteId ? null : $loteId;
    }

    #[On('codigosGenerados')]
    public function alGenerar(int $loteId, string $tamano): void
    {
        $this->tamano = $tamano;
        $this->abierto = $loteId;
        $this->resetPage('lotes');
    }

    public function updatedTamano(): void
    {
        if (!array_key_exists($this->tamano, CodigoBarrasController::TAMANOS)) {
            $this->tamano = CodigoBarrasController::TAMANO_POR_DEFECTO;
        }
    }

    public function render()
    {
        $lotes = CodigoLote::with('user:id,name')->latest('id')->paginate(15, ['*'], 'lotes');

        // Asignado = aparece como upc o sku de algun equipo o articulo. Se
        // cuenta por lote en una consulta (no se guarda: ver CodigoBarras).
        $asignados = $lotes->isEmpty() ? collect() : DB::table('codigos_barras as cb')
            ->joinSub(CodigoBarras::consultaDuenos(), 'u', 'u.codigo', '=', 'cb.codigo')
            ->whereIn('cb.lote_id', $lotes->pluck('id'))
            ->groupBy('cb.lote_id')
            ->selectRaw('cb.lote_id, COUNT(DISTINCT cb.id) as asignados')
            ->pluck('asignados', 'lote_id');

        $codigos = collect();
        $duenos = collect();

        if ($this->abierto) {
            $codigos = CodigoBarras::where('lote_id', $this->abierto)->orderBy('secuencia')->pluck('codigo');
            $duenos = CodigoBarras::duenos($codigos->all());
        }

        return view('livewire.codigo.codigo-index', [
            'lotes' => $lotes,
            'asignados' => $asignados,
            'codigos' => $codigos,
            'duenos' => $duenos,
            'tamanos' => CodigoBarrasController::TAMANOS,
        ]);
    }
}
