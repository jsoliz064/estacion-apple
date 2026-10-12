<?php

namespace App\Livewire\ArticuloHistorial;

use App\Enums\ArticuloTipo;
use App\Models\MovimientoStock;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * El historial de un repuesto o un accesorio: dos pestañas, Movimientos (a
 * donde fue el stock, el UNION de MovimientoStock) y Cambios (lo que una
 * persona hizo sobre la ficha, la bitacora).
 */
class ArticuloHistorialIndex extends Component
{
    #[Locked]
    public string $tipo = '';

    #[Locked]
    public int $articuloId = 0;

    /** Entradas − Salidas según los documentos. Debe cuadrar con el stock. */
    public int $balanceCalculado = 0;

    public function mount(string $tipo, $articulo_id)
    {
        $tipoEnum = ArticuloTipo::from($tipo);
        abort_unless(auth()->user()?->can($tipoEnum->permiso() . '.historial'), 403);

        $this->tipo = $tipoEnum->value;
        $this->articuloId = ($tipoEnum->modelo())::findOrFail($articulo_id)->id;

        $fila = MovimientoStock::paraArticulo($tipoEnum, $this->articuloId)
            ->toBase()
            ->selectRaw("COALESCE(SUM(CASE WHEN direccion = 'Entrada' THEN cantidad ELSE -cantidad END), 0) as saldo")
            ->first();

        $this->balanceCalculado = (int) ($fila->saldo ?? 0);
    }

    public function render()
    {
        $tipo = ArticuloTipo::from($this->tipo);
        $relaciones = $tipo === ArticuloTipo::Repuesto
            ? ['stocks.sucursal', 'categoria', 'modelo']
            : ['stocks.sucursal', 'categoria', 'modelosCompatibles'];

        return view('livewire.articulo-historial.index', [
            'tipoEnum' => $tipo,
            'articulo' => ($tipo->modelo())::with($relaciones)->findOrFail($this->articuloId),
        ]);
    }
}
