<?php

namespace App\Livewire\Dashboard;

use App\Models\ProductoModelo;
use App\Models\Sucursal;
use App\Support\ResumenInventario;
use Livewire\Component;

/**
 * Los equipos en inventario por sucursal y por modelo + almacenamiento, a la
 * vista en el inicio, apenas se entra. Los conteos salen de ResumenInventario.
 * Componente aparte para que mover un filtro no vuelva a calcular las tarjetas
 * de ventas ni las tablas del tablero.
 */
class InventarioPorModelo extends Component
{
    /** '' = todos. */
    public $modeloId = '';

    /** '' = todas. */
    public $sucursalId = '';

    public function render()
    {
        $modeloId = $this->modeloId !== '' ? (int) $this->modeloId : null;

        // Solo los modelos con stock: la lista entera del catalogo es ruido.
        // El elegido se queda aunque se haya vendido el ultimo, o el select
        // quedaria en un valor que no ofrece.
        $modelos = ProductoModelo::query()
            ->where(fn($q) => $q
                ->whereHas('productos', fn($p) => $p->disponibles())
                ->when($modeloId, fn($q) => $q->orWhere('id', $modeloId)))
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return view('livewire.dashboard.inventario-por-modelo', [
            'bloques' => ResumenInventario::bloques($modeloId, $this->sucursalId !== '' ? (int) $this->sucursalId : null),
            'modelos' => $modelos,
            // Los filtros usan todas las sucursales, tambien las desactivadas.
            'sucursales' => Sucursal::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }
}
