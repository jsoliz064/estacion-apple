<?php

namespace App\Livewire\Articulo\Modals;

use App\Enums\ArticuloTipo;
use App\Enums\BajaMotivo;
use App\Services\BajaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Dar de baja unidades de un repuesto o accesorio (perdidas, danadas,
 * robadas...): salen del stock de una sucursal y quedan registradas con su
 * motivo y su costo para el reporte de perdidas (BajaService).
 */
class StockBajaModal extends Component
{
    public $openModal = false;

    #[Locked]
    public string $tipo = '';

    #[Locked]
    public ?int $articuloId = null;

    public $sucursal_id = '';
    public $cantidad = null;
    public $motivo = '';
    public $nota = '';

    protected function rules(): array
    {
        return [
            'sucursal_id' => 'required|integer|exists:sucursales,id',
            'cantidad' => 'required|integer|min:1',
            'motivo' => ['required', Rule::in(BajaMotivo::valoresManuales())],
            'nota' => 'nullable|string|max:255',
        ];
    }

    protected $messages = [
        'sucursal_id.required' => 'Elige la sucursal de donde salen las unidades.',
        'cantidad.required' => 'Indica cuántas unidades dar de baja.',
        'cantidad.min' => 'La cantidad debe ser al menos 1.',
        'motivo.required' => 'Elige el motivo de la baja.',
    ];

    private function tipoEnum(): ArticuloTipo
    {
        return ArticuloTipo::from($this->tipo);
    }

    #[On('openStockBajaModal')]
    public function openModal(string $tipo, $id): void
    {
        $this->reset(['sucursal_id', 'cantidad', 'motivo', 'nota']);
        $this->resetValidation();

        $this->tipo = ArticuloTipo::from($tipo)->value;
        $this->articuloId = (int) $id;
        $this->openModal = true;
    }

    public function store(): void
    {
        abort_unless(Auth::user()->can($this->tipoEnum()->permiso() . '.baja'), 403);

        $this->validate();

        DB::transaction(fn() => app(BajaService::class)->darDeBajaStock(
            $this->tipoEnum(),
            $this->articuloId,
            (int) $this->sucursal_id,
            (int) $this->cantidad,
            BajaMotivo::from($this->motivo),
            $this->nota,
        ));

        $this->dispatch('refreshArticuloTable');
        toastr()->success('Baja registrada: las unidades salieron del stock.');
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->reset();
    }

    public function render()
    {
        $articulo = null;
        $reparto = collect();

        if ($this->openModal && $this->articuloId) {
            $articulo = ($this->tipoEnum()->modelo())::with('stocks.sucursal')->find($this->articuloId);
            $reparto = $articulo ? $articulo->stocks->sortByDesc('cantidad')->values() : collect();
        }

        return view('livewire.articulo.modals.stock-baja-modal', [
            'articulo' => $articulo,
            'reparto' => $reparto,
            'origenes' => $reparto->filter(fn($s) => (int) $s->cantidad > 0)->values(),
        ]);
    }
}
