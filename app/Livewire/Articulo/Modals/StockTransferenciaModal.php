<?php

namespace App\Livewire\Articulo\Modals;

use App\Enums\ArticuloTipo;
use App\Models\Sucursal;
use App\Services\StockService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Mueve unidades de un repuesto o accesorio de una sucursal a otra, dejando su
 * documento en stock_transferencias (sin el, el movimiento seria invisible en
 * el historial y el saldo no cuadraria).
 *
 * UN articulo por transferencia, fijado desde la fila que abrio el modal.
 */
class StockTransferenciaModal extends Component
{
    public $openModal = false;

    /** #[Locked]: deciden de que stock se saca; sin candado un payload movería otro. */
    #[Locked]
    public string $tipo = '';

    #[Locked]
    public ?int $articuloId = null;

    /**
     * Los nombres sucursal1_id / sucursal2_id NO son arbitrarios:
     * StockService::transferir() lanza su error de origen-igual-a-destino bajo
     * la clave 'sucursal2_id'.
     */
    public $sucursal1_id = '';
    public $sucursal2_id = '';
    public $cantidad = null;

    protected function rules(): array
    {
        return [
            'sucursal1_id' => 'required|integer|exists:sucursales,id',
            'sucursal2_id' => 'required|integer|exists:sucursales,id,activa,1|different:sucursal1_id',
            'cantidad' => 'required|integer|min:1',
        ];
    }

    protected $messages = [
        'sucursal1_id.required' => 'Elige la sucursal de origen.',
        'sucursal2_id.required' => 'Elige la sucursal de destino.',
        'sucursal2_id.different' => 'El origen y el destino tienen que ser sucursales distintas.',
        'sucursal2_id.exists' => 'La sucursal de destino no existe o está desactivada.',
        'cantidad.required' => 'Indica cuántas unidades transferir.',
        'cantidad.min' => 'La cantidad debe ser al menos 1.',
    ];

    private function tipoEnum(): ArticuloTipo
    {
        return ArticuloTipo::from($this->tipo);
    }

    #[On('openStockTransferenciaModal')]
    public function openModal(string $tipo, $id): void
    {
        $this->reset(['sucursal1_id', 'sucursal2_id', 'cantidad']);
        $this->resetValidation();

        $this->tipo = ArticuloTipo::from($tipo)->value;
        $this->articuloId = (int) $id;
        $this->openModal = true;
    }

    public function store(): void
    {
        // El evento es invocable desde el cliente: el permiso se revalida aqui.
        abort_unless(Auth::user()->can($this->tipoEnum()->permiso() . '.transferir'), 403);

        $this->validate();

        $stock = app(StockService::class);
        $cantidad = (int) $this->cantidad;

        // Aviso amable ANTES de intentarlo. NO es la guarda: la de verdad es el
        // `WHERE cantidad >= ?` de retirar().
        $hay = $stock->disponible($this->tipoEnum(), $this->articuloId, (int) $this->sucursal1_id);

        if ($hay < $cantidad) {
            $this->addError('cantidad', "Solo hay {$hay} unidad(es) en la sucursal de origen.");

            return;
        }

        DB::transaction(function () use ($stock, $cantidad) {
            $stock->transferir($this->tipoEnum(), $this->articuloId, (int) $this->sucursal1_id, (int) $this->sucursal2_id, $cantidad, Auth::id());
            // transferir() NO lo llama: sin esto el total cacheado se queda atras.
            $stock->recalcularTotales();
        });

        $this->dispatch('refreshArticuloTable');
        toastr()->success('Stock transferido exitosamente');
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->reset();
    }

    public function render()
    {
        // Variables de vista: no viajan en el payload. Cerrado, 0 consultas.
        $articulo = null;
        $reparto = collect();

        if ($this->openModal && $this->articuloId) {
            $articulo = ($this->tipoEnum()->modelo())::with('stocks.sucursal')->find($this->articuloId);
            $reparto = $articulo ? $articulo->stocks->sortByDesc('cantidad')->values() : collect();
        }

        return view('livewire.articulo.modals.stock-transferencia-modal', [
            'articulo' => $articulo,
            'reparto' => $reparto,
            // Una sucursal sin unidades no es un origen posible. El origen puede
            // ser una inactiva (para vaciarla); el destino, solo activas.
            'origenes' => $reparto->filter(fn($s) => (int) $s->cantidad > 0)->values(),
            'sucursales' => $this->openModal ? Sucursal::activas()->orderBy('nombre')->get() : collect(),
        ]);
    }
}
