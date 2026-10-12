<?php

namespace App\Livewire\Articulo;

use App\Enums\ArticuloTipo;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

/**
 * El listado de repuestos o de accesorios. El tipo llega por parametro de
 * montaje con #[Locked] (ver ArticuloIndex), y el modelo del paquete sale de el.
 */
class ArticuloTable extends DataTableComponent
{
    #[Locked]
    public string $tipo = '';

    /** Boton de camara en la busqueda (vendor/livewire-tables/.../search-field). */
    public bool $buscarConEscaner = true;

    public $selectedModelos = [];
    public $selectedCategorias = [];
    public $selectedMinStock = 0;

    public function mount(string $tipo): void
    {
        $this->tipo = ArticuloTipo::from($tipo)->value;
    }

    public function tipoEnum(): ArticuloTipo
    {
        return ArticuloTipo::from($this->tipo);
    }

    private function esRepuesto(): bool
    {
        return $this->tipoEnum() === ArticuloTipo::Repuesto;
    }

    #[On('filtersUpdated')]
    public function updateTableFilters($filters)
    {
        $this->selectedModelos = $filters['selectedModelos'] ?? [];
        $this->selectedCategorias = $filters['selectedCategorias'] ?? [];
        $this->selectedMinStock = $filters['selectedMinStock'] ?? 0;
        // El tipo NO llega por aqui: lo fija la ruta en mount().

        $this->setBuilder($this->builder());
    }

    public function configure(): void
    {
        // Nombre propio: el default del paquete es 'table' para todas.
        $this->setTableName($this->tipoEnum()->tabla());

        $this->setPrimaryKey('id')
            ->setDefaultSort($this->tipoEnum()->tabla() . '.nombre', 'asc')
            ->setSearchPlaceholder('Buscar por nombre, SKU o código de barras...')
            ->setEmptyMessage('Todavía no hay ' . mb_strtolower($this->tipoEnum()->plural()) . ' registrados.');

        $tabla = $this->tipoEnum()->tabla();
        $extra = ["{$tabla}.upc"];
        if ($this->esRepuesto()) {
            $extra = array_merge($extra, ['repuestos.color', 'repuestos.color_hex']);
        }
        $this->setAdditionalSelects($extra);
    }

    public function columns(): array
    {
        $tabla = $this->tipoEnum()->tabla();
        $modelo = $this->tipoEnum()->modelo();
        $vacio = fn($value) => $value !== null && $value !== '' ? e($value) : '<span class="text-gray-400">—</span>';

        $columnas = [
            Column::make('Id', 'id')->sortable(),
            Column::make('SKU', 'sku')
                ->sortable()
                ->format($vacio)
                ->html()
                // SKU y codigo de barras: el lector "teclea" aqui tambien.
                ->searchable(fn(Builder $q, $term) => $q
                    ->orWhere("{$tabla}.sku", 'like', '%' . $term . '%')
                    ->orWhere("{$tabla}.upc", $term)),
        ];

        if ($this->esRepuesto()) {
            $columnas[] = Column::make('Nombre', 'nombre')
                ->format(fn($value, $row) => $row->getNombreConColor())
                ->html()
                ->sortable()
                ->searchable(fn(Builder $q, $term) => $q
                    ->orWhere('repuestos.nombre', 'like', '%' . $term . '%')
                    ->orWhere('repuestos.color', 'like', '%' . $term . '%'));
            $columnas[] = Column::make('Fabricante', 'fabricante')->sortable()->searchable()->format($vacio)->html()->collapseOnTablet();
            $columnas[] = Column::make('Categoría', 'categoria.nombre')->sortable()->searchable();
            $columnas[] = Column::make('Modelo', 'modelo.nombre')->sortable()->searchable();
        } else {
            $columnas[] = Column::make('Nombre', 'nombre')->sortable()->searchable();
            $columnas[] = Column::make('Marca', 'marca')->sortable()->searchable()->format($vacio)->html()->collapseOnTablet();
            $columnas[] = Column::make('Categoría', 'categoria.nombre')->sortable()->searchable();
            $columnas[] = Column::make('Compatible con')
                ->label(fn($row) => $row->modelosCompatibles->isEmpty()
                    ? '<span class="text-gray-400">—</span>'
                    : '<span class="text-xs">' . e($row->modelosCompatibles->pluck('nombre')->implode(', ')) . '</span>')
                ->html()
                ->setCustomSlug('compatibles')
                ->collapseOnTablet();
        }

        return array_merge($columnas, [
            // Rotulo pedido por el cliente: el costo se lee como "codigo".
            Column::make('Costo (código)', 'costo')
                ->sortable()
                ->format(fn($v) => 'Bs ' . number_format((float) $v, 2))
                ->collapseOnTablet(),
            Column::make('Precio', 'precio')
                ->sortable()
                ->format(fn($v) => 'Bs ' . number_format((float) $v, 2)),
            Column::make('Cantidad', 'cantidad')
                // Rojo si alguna sucursal llego a su minimo (el desglose dice cual).
                ->format(fn($value, $row) => $row->estaPorAgotarse()
                    ? '<span class="text-red-600 font-semibold">' . $value . '</span>'
                    : '<span class="text-green-600 font-semibold">' . $value . '</span>')
                ->html()
                ->sortable(),
            // No ordenable a proposito: son varias filas de la subtabla.
            Column::make('Por sucursal')
                ->label(fn($row) => $row->desgloseStock())
                ->html()
                ->setCustomSlug('por-sucursal')
                ->collapseOnTablet(),
            Column::make('Acciones', 'id')
                ->format(fn($value, $row) => view('livewire.articulo.actions-buttons', [
                    'row' => $row,
                    'tipo' => $this->tipoEnum(),
                ])),
        ]);
    }

    public function builder(): Builder
    {
        $tipo = $this->tipoEnum();
        $modelo = $tipo->modelo();

        return $modelo::query()
            // El desglose por sucursal de la pagina en dos consultas, no dos por fila.
            ->with(array_filter([
                'stocks.sucursal:id,nombre',
                $this->esRepuesto() ? null : 'modelosCompatibles:id,nombre',
            ]))
            ->when((bool) $this->selectedMinStock, fn($q) => $q->bajoStock())
            ->when(!empty($this->selectedModelos), function ($q) {
                $this->esRepuesto()
                    ? $q->whereIn('repuestos.producto_modelo_id', $this->selectedModelos)
                    : $q->whereHas('modelosCompatibles', fn($m) => $m->whereIn('productos_modelos.id', $this->selectedModelos));
            })
            ->when(!empty($this->selectedCategorias), fn($q) => $q->whereIn(
                $this->esRepuesto() ? 'repuestos.repuesto_categoria_id' : 'accesorios.accesorio_categoria_id',
                $this->selectedCategorias
            ));
    }

    #[On('refreshArticuloTable')]
    public function refreshArticuloTable()
    {
        $this->builder();
    }

    public function openArticuloEditModal($id)
    {
        $this->dispatch('openArticuloEditModal', $id);
    }

    public function openArticuloDestroyModal($id)
    {
        $this->dispatch('openArticuloDestroyModal', $id);
    }

    public function openStockTransferenciaModal($id)
    {
        $this->dispatch('openStockTransferenciaModal', $this->tipo, $id);
    }

    public function openStockBajaModal($id)
    {
        $this->dispatch('openStockBajaModal', $this->tipo, $id);
    }

    public function openArticuloHistorial($id)
    {
        return redirect()->route($this->tipoEnum()->rutaHistorial(), $id);
    }
}
