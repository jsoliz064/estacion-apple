<?php

namespace App\Livewire\Producto;

use App\Enums\ProductoAlmacenamiento;
use App\Enums\ProductoEstado;
use App\Enums\ProductoTipoVenta;
use App\Enums\ProductoVersion;
use App\Models\Producto;
use App\Models\ProductoModelo;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ProductoFilterModel extends Component
{
    public $models;
    public $selectedModelId = null;

    public $showDetailsModal = false;
    public $modelDetailsData = [];
    public $textToCopy = '';

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

    public function showModelDetails($modelId)
    {
        $model = ProductoModelo::find($modelId);
        if (!$model)
            return;

        $query = Producto::where('producto_modelo_id', $modelId)
            ->disponibles()
            ->whereNotNull('sucursal_id');

        $total = (clone $query)->count();

        $colors = (clone $query)
            ->select('color', DB::raw('count(*) as total'))
            ->groupBy('color')
            ->orderByDesc('total')
            ->get();

        $estados = (clone $query)
            ->select('estado_grado', DB::raw('count(*) as total'))
            ->groupBy('estado_grado')
            ->orderByDesc('total')
            ->get();

        // Las capacidades en el orden del enum (128GB antes que 1TB), no por
        // cantidad: se leen como una escala. Sin almacenamiento (un Apple
        // Watch), al final.
        $orden = array_column(ProductoAlmacenamiento::cases(), 'value');
        $posicion = fn($almacenamiento) => $almacenamiento === null ? 999
            : (($i = array_search($almacenamiento, $orden, true)) === false ? 998 : $i);
        $nombreCapacidad = fn($almacenamiento) => $almacenamiento ?: 'Sin almacenamiento';

        $almacenamientos = (clone $query)
            ->select('almacenamiento', DB::raw('count(*) as total'))
            ->groupBy('almacenamiento')
            ->get()
            ->sortBy(fn($a) => $posicion($a->almacenamiento))
            ->values();

        // El tipo es la version (Chip, eSIM, Solo Wi-Fi), y dentro de cada uno
        // sus capacidades: lo que la tienda pregunta al ofrecer un equipo.
        $nombreTipo = fn($version) => $version ? ProductoVersion::labelDe($version) : 'Sin definir';

        $tipos = (clone $query)
            ->select('version', DB::raw('count(*) as total'))
            ->groupBy('version')
            ->orderByDesc('total')
            ->get()
            ->map(fn($t) => ['etiqueta' => $nombreTipo($t->version), 'total' => (int) $t->total])
            ->all();

        $porTipo = (clone $query)
            ->select('version', 'almacenamiento', DB::raw('count(*) as total'))
            ->groupBy('version', 'almacenamiento')
            ->get()
            ->groupBy(fn($c) => $nombreTipo($c->version));

        // Los grupos en el orden de «Por Tipo» (el de mas equipos primero).
        $capacidadesPorTipo = [];
        foreach ($tipos as $tipo) {
            $capacidadesPorTipo[$tipo['etiqueta']] = $porTipo->get($tipo['etiqueta'], collect())
                ->sortBy(fn($c) => $posicion($c->almacenamiento))
                ->map(fn($c) => ['etiqueta' => $nombreCapacidad($c->almacenamiento), 'total' => (int) $c->total])
                ->values()
                ->all();
        }

        $colorsByEstado = (clone $query)
            ->select('color', 'estado_grado', DB::raw('count(*) as total'))
            ->groupBy('estado_grado', 'color')
            ->orderBy('estado_grado')
            ->orderByDesc('total')
            ->get();

        $this->modelDetailsData = [
            'nombre' => $model->nombre,
            'total' => $total,
            'colors' => $colors,
            'estados' => $estados,
            'almacenamientos' => $almacenamientos,
            'colorsByEstado' => $colorsByEstado,
            'tipos' => $tipos,
            'capacidadesPorTipo' => $capacidadesPorTipo,
        ];

        // Generar texto para copiar
        $text = "*Modelo:* {$model->nombre}\n";
        $text .= "*Total en stock:* {$total}\n\n";

        if ($colors->isNotEmpty()) {
            $text .= "*Colores (General):*\n";
            foreach ($colors as $c) {
                $val = $c->color ?: 'Sin especificar';
                $text .= "- {$val}: {$c->total}\n";
            }
            $text .= "\n";
        }

        if ($colorsByEstado->isNotEmpty()) {
            $text .= "*Colores según Grado/Estado:*\n";
            $currentEstado = null;
            foreach ($colorsByEstado as $ce) {
                $estado = $ce->estado_grado ?: 'Sin especificar';
                $color = $ce->color ?: 'Sin especificar';

                if ($estado !== $currentEstado) {
                    $text .= "- Grado {$estado}:\n";
                    $currentEstado = $estado;
                }
                $text .= "  • {$color}: {$ce->total}\n";
            }
            $text .= "\n";
        }

        if ($estados->isNotEmpty()) {
            $text .= "*Estado/Grado (General):*\n";
            foreach ($estados as $e) {
                $val = $e->estado_grado ?: 'Sin especificar';
                $text .= "- {$val}: {$e->total}\n";
            }
            $text .= "\n";
        }

        if ($tipos) {
            $text .= "*Tipo:*\n";
            foreach ($tipos as $t) {
                $text .= "- {$t['etiqueta']}: {$t['total']}\n";
            }
            $text .= "\n*Capacidad según Tipo:*\n";
            foreach ($capacidadesPorTipo as $tipo => $capacidades) {
                $text .= "- {$tipo}:\n";
                foreach ($capacidades as $c) {
                    $text .= "  • {$c['etiqueta']}: {$c['total']}\n";
                }
            }
            $text .= "\n";
        }

        if ($almacenamientos->isNotEmpty()) {
            $text .= "*Almacenamiento:*\n";
            foreach ($almacenamientos as $a) {
                $val = $nombreCapacidad($a->almacenamiento);
                $text .= "- {$val}: {$a->total}\n";
            }
        }

        $this->textToCopy = trim($text);

        $this->showDetailsModal = true;
    }

    public function closeDetailsModal()
    {
        $this->showDetailsModal = false;
        $this->modelDetailsData = [];
        $this->textToCopy = '';
    }

    public function render()
    {
        $this->loadModels();
        return view('livewire.producto.producto-filter-model');
    }
}