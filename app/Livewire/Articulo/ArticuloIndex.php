<?php

namespace App\Livewire\Articulo;

use App\Enums\ArticuloTipo;
use App\Models\AccesorioCategoria;
use App\Models\ProductoModelo;
use App\Models\RepuestoCategoria;
use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * El inventario de repuestos o de accesorios. Son dos tablas distintas
 * (repuestos / accesorios) con la misma pantalla: el tipo lo fija la RUTA.
 *
 * El tipo llega por mount() y no por evento: applyFilters() despacha
 * 'filtersUpdated' durante el mount, cuando ArticuloTable todavia no existe,
 * asi que nadie recibe ese primer evento. #[Locked] porque un payload podria
 * cambiarlo y ver el otro listado con los permisos de este.
 */
class ArticuloIndex extends Component
{
    #[Locked]
    public string $tipo = '';

    public $selectedModelos = [];
    public $selectedCategorias = [];
    public $selectedMinStock = 0;

    public $totalArticulos = 0;
    public $totalBajoStock = 0;

    /** [['nombre', 'unidades', 'articulos'], ...] */
    public $sucursalesResumen = [];

    public function mount(string $tipo)
    {
        // from() y no tryFrom(): un tipo invalido tiene que reventar.
        $this->tipo = ArticuloTipo::from($tipo)->value;
        $this->applyFilters();
    }

    public function tipoEnum(): ArticuloTipo
    {
        return ArticuloTipo::from($this->tipo);
    }

    public function esRepuesto(): bool
    {
        return $this->tipoEnum() === ArticuloTipo::Repuesto;
    }

    /** 'repuesto' o 'accesorio': el prefijo de los @can de esta pantalla. */
    public function permiso(): string
    {
        return $this->tipoEnum()->permiso();
    }

    public function render()
    {
        return view('livewire.articulo.articulo-index', [
            'modelos' => ProductoModelo::orderBy('nombre')->get(['id', 'nombre']),
            'categorias' => $this->esRepuesto()
                ? RepuestoCategoria::orderBy('nombre')->get(['id', 'nombre'])
                : AccesorioCategoria::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function openArticuloCreateModal()
    {
        $this->dispatch('openArticuloCreateModal');
    }

    /**
     * Los articulos de ESTA pantalla con sus filtros. Fuente unica: la usan los
     * dos contadores y el resumen por sucursal, que tienen que cuadrar.
     *
     * En repuestos, "modelos" es el modelo al que encaja la pieza; en
     * accesorios, los modelos con los que es compatible (muchos a muchos).
     */
    private function articulosFiltrados(): Builder
    {
        $tipo = $this->tipoEnum();
        $modelo = $tipo->modelo();

        return $modelo::query()
            ->when(!empty($this->selectedModelos), function ($q) {
                $this->esRepuesto()
                    ? $q->whereIn('producto_modelo_id', $this->selectedModelos)
                    : $q->whereHas('modelosCompatibles', fn($m) => $m->whereIn('productos_modelos.id', $this->selectedModelos));
            })
            ->when(!empty($this->selectedCategorias), fn($q) => $q->whereIn(
                $this->esRepuesto() ? 'repuesto_categoria_id' : 'accesorio_categoria_id',
                $this->selectedCategorias
            ))
            ->when((bool) $this->selectedMinStock, fn($q) => $q->bajoStock());
    }

    public function calculateTotals()
    {
        $this->totalArticulos = $this->articulosFiltrados()->count();
        // Por agotarse en alguna sucursal (minimo por articulo y sucursal).
        $this->totalBajoStock = $this->articulosFiltrados()->bajoStock()->count();
        $this->sucursalesResumen = $this->resumenPorSucursal();
    }

    /**
     * Unidades y articulos distintos por sucursal, con los filtros puestos.
     *
     * withSum sobre sucursales (subconsultas correlacionadas) y no un GROUP BY
     * sobre el stock: asi una sucursal SIN filas de stock sigue saliendo, en 0.
     * Solo sucursales activas, mas las inactivas que todavia guardan algo.
     */
    private function resumenPorSucursal(): array
    {
        $tipo = $this->tipoEnum();
        $col = $tipo->columna();
        $articulos = $this->articulosFiltrados()->select($tipo->tabla() . '.id');

        return Sucursal::query()
            ->withSum(['stocks as unidades' => fn($q) => $q->whereIn($col, $articulos)], 'cantidad')
            ->withCount(['stocks as articulos' => fn($q) => $q
                ->whereIn($col, $articulos)
                // Una fila en cero es el rastro de que lo hubo, no "hay aqui".
                ->where('cantidad', '<>', 0)])
            ->orderBy('nombre')
            ->get()
            ->filter(fn($s) => $s->activa || (int) $s->unidades !== 0)
            ->map(fn($s) => [
                'nombre' => $s->nombre,
                // withSum da NULL cuando no hay ni una fila.
                'unidades' => (int) ($s->unidades ?? 0),
                'articulos' => (int) $s->articulos,
            ])
            ->values()
            ->all();
    }

    public function updated($propertyName)
    {
        if (str_starts_with($propertyName, 'selectedModelos')
            || str_starts_with($propertyName, 'selectedCategorias')
            || $propertyName === 'selectedMinStock') {
            $this->applyFilters();
        }
    }

    public function resetFilters()
    {
        $this->reset(['selectedModelos', 'selectedMinStock', 'selectedCategorias']);
        $this->applyFilters();
    }

    public function applyFilters()
    {
        $this->calculateTotals();

        $this->dispatch('filtersUpdated', [
            'selectedModelos' => $this->selectedModelos,
            'selectedMinStock' => $this->selectedMinStock,
            'selectedCategorias' => $this->selectedCategorias,
        ]);
    }
}
