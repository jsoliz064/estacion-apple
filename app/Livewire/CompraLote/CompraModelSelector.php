<?php

namespace App\Livewire\CompraLote;

use App\Enums\ProductoEstado;
use App\Enums\ProductoTipoVenta;
use App\Models\Producto;
use App\Models\ProductoModelo;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class CompraModelSelector extends Component
{
    public $models;
    public $compraId;

    public function mount($compraId)
    {
        $this->compraId = is_numeric($compraId) ? (int)$compraId : null;
    }

    #[On('loadModelCounts')]
    public function loadModelCounts()
    {
        $models = ProductoModelo::orderBy('id')->get();
        $sucursales = Sucursal::orderBy('nombre')->get();

        // Los equipos de ESTA compra (por su linea), vigentes y sin vender, en
        // una sola consulta agregada. "oferta" es un subconteo de inventario
        // (tipo_venta = Oferta): ya no es un estado.
        $counts = Producto::query()
            ->vigentes()
            ->whereNotIn('productos.estado', ProductoEstado::vendidos())
            ->whereHas('compraDetalle', fn($q) => $q->where('compra_id', $this->compraId))
            ->whereNotNull('sucursal_id')
            ->select('producto_modelo_id', 'sucursal_id', 'estado', 'tipo_venta', DB::raw('COUNT(*) as total'))
            ->groupBy('producto_modelo_id', 'sucursal_id', 'estado', 'tipo_venta')
            ->get();

        $vacio = ['inventario' => 0, 'reserva' => 0, 'reparacion' => 0, 'fuera' => 0, 'roto' => 0, 'oferta' => 0];
        $procesado = [];

        foreach ($counts as $fila) {
            $celda = &$procesado[$fila->producto_modelo_id][$fila->sucursal_id];
            $celda ??= $vacio;
            $clave = strtolower($fila->estado);

            if (array_key_exists($clave, $celda)) {
                $celda[$clave] += $fila->total;
            }
            if ($fila->estado === ProductoEstado::Inventario->value && $fila->tipo_venta === ProductoTipoVenta::Oferta->value) {
                $celda['oferta'] += $fila->total;
            }
            unset($celda);
        }

        foreach ($models as $model) {
            $model->sucursales_summary = collect();
            $totalProductos = 0;

            foreach ($sucursales as $sucursal) {
                $datos = $procesado[$model->id][$sucursal->id] ?? $vacio;
                $summary = ['nombre' => $sucursal->nombre] + $datos;
                // La oferta ya esta dentro de inventario: no se suma dos veces.
                $summary['total'] = $datos['inventario'] + $datos['reserva'] + $datos['reparacion'] + $datos['fuera'] + $datos['roto'];
                $totalProductos += $summary['total'];
                $model->sucursales_summary->put($sucursal->id, $summary);
            }

            $model->productos_count = $totalProductos;
        }

        $this->models = $models;
    }

    public function openModalSelector($modelId)
    {
        $this->dispatch('openModalSelector', modelId: $modelId);
    }

    public function render()
    {
        $this->loadModelCounts();
        return view('livewire.compra-lote.compra-model-selector');
    }
}
