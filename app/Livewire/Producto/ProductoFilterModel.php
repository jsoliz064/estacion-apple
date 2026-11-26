<?php

namespace App\Livewire\Producto;

use App\Enums\ProductoEstado;
use App\Enums\ProductoTipoVenta;
use App\Models\Producto;
use App\Models\ProductoModelo;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ProductoFilterModel extends Component
{
    public $models;
    public $selectedModelId = null;

    public function loadModels()
    {
        // 1. Obtenemos todos los modelos y sucursales
        $models = ProductoModelo::orderBy('id')->get();
        $sucursales = Sucursal::all();

        // 2. Todos los conteos en una sola consulta, sin los dados de baja
        //    (archivados). La oferta es un SUBCONTEO del inventario
        //    (tipo_venta = Oferta), no un estado: se cuenta aparte y no suma al
        //    total, o esos equipos contarian doble.
        $counts = Producto::query()
            ->vigentes()
            ->select('producto_modelo_id', 'sucursal_id', 'estado', DB::raw('COUNT(*) as total'))
            ->selectRaw('SUM(tipo_venta = ?) as ofertas', [ProductoTipoVenta::Oferta->value])
            ->whereNotNull('sucursal_id')
            ->groupBy('producto_modelo_id', 'sucursal_id', 'estado')
            ->get();

        // Los estados que son stock (todo menos lo vendido).
        $claves = ['inventario', 'reparacion', 'fuera', 'roto', 'reserva'];

        // 3. Procesamos los datos para estructurarlos
        $processedCounts = [];
        foreach ($counts as $countData) {
            $modelId = $countData->producto_modelo_id;
            $sucursalId = $countData->sucursal_id;
            $estadoKey = strtolower((string) $countData->estado);

            $processedCounts[$modelId][$sucursalId] ??= array_fill_keys([...$claves, 'oferta'], 0);

            if (in_array($estadoKey, $claves, true)) {
                $processedCounts[$modelId][$sucursalId][$estadoKey] += $countData->total;
            }

            if ($estadoKey === 'inventario') {
                $processedCounts[$modelId][$sucursalId]['oferta'] += (int) $countData->ofertas;
            }
        }

        // 4. Llenamos los modelos con los datos resumidos
        foreach ($models as $model) {
            $model->sucursales_summary = collect();
            $totalProductos = 0;

            foreach ($sucursales as $sucursal) {
                $summary = ['nombre' => $sucursal->nombre, 'total' => 0] + array_fill_keys([...$claves, 'oferta'], 0);

                if (isset($processedCounts[$model->id][$sucursal->id])) {
                    $summary = array_merge($summary, $processedCounts[$model->id][$sucursal->id]);
                    $summary['total'] = array_sum(array_intersect_key($summary, array_flip($claves)));
                }

                $totalProductos += $summary['total'];

                $model->sucursales_summary->put($sucursal->id, $summary);
            }

            $model->productos_count = $totalProductos;
        }

        $this->models = $models;
    }

    public function selectModel($modelId)
    {
        $this->selectedModelId = $modelId;
        $this->dispatch('modelSelected', $modelId);
    }

    public function clearSelection()
    {
        $this->selectedModelId = null;
        $this->dispatch('modelCleared');
    }

    public function render()
    {
        $this->loadModels();
        return view('livewire.producto.producto-filter-model');
    }
}