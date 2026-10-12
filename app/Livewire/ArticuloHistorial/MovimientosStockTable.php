<?php

namespace App\Livewire\ArticuloHistorial;

use App\Enums\ArticuloTipo;
use App\Enums\MovimientoStockTipo;
use App\Models\MovimientoStock;
use App\Models\Sucursal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\DateFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\MultiSelectDropdownFilter;

class MovimientosStockTable extends DataTableComponent
{
    /** Tabla VIRTUAL (UNION). Ver el docblock de MovimientoStock. */
    protected $model = MovimientoStock::class;

    #[Locked]
    public string $tipo = '';

    #[Locked]
    public int $articuloId = 0;

    /** Memo por request de la fila de totales. */
    protected ?object $totales = null;

    public function mount(string $tipo, $articulo_id)
    {
        $this->tipo = ArticuloTipo::from($tipo)->value;
        $this->articuloId = (int) $articulo_id;
    }

    public function configure(): void
    {
        // Nombre propio: el default del paquete es 'table' para TODAS las tablas
        // (query string, id del DOM y evento Alpine de filas colapsadas).
        $this->setTableName('movimientos');

        $this->setPrimaryKey('id')
            ->setDefaultSort('fecha', 'desc')
            ->setSearchPlaceholder('Buscar por detalle, tipo, usuario o nº de documento...')
            ->setEmptyMessage('Este artículo no registra compras, ventas, reparaciones, regalos, bajas ni transferencias.');

        // El paquete solo SELECTea los campos de las columnas declaradas, pero
        // executeQuery() hace pluck('id') para los wire:key, y el format de
        // Cantidad necesita $row->direccion (que no es una columna visible).
        $this->setAdditionalSelects([
            'movimientos_stock.id',
            'movimientos_stock.direccion',
        ]);

        $this->setFooterTrAttributes(fn($rows) => [
            'default' => false,
            'class' => 'bg-gray-100 dark:bg-gray-900 font-bold text-gray-900 dark:text-white',
        ]);
    }

    /**
     * Query base compartida por builder() y getTotales().
     *
     * TODO filtro y TODA búsqueda se aplican AQUÍ, nunca con ->filter() en
     * filters() ni con ->searchable() en columns(): applyFilters() y
     * applySearch() (Traits/WithData.php::baseQuery) tocan solo el builder de
     * las filas, no la query agregada del footer, y los totales quedarían
     * descuadrados respecto a lo que se ve.
     */
    protected function scopedQuery(): Builder
    {
        $query = MovimientoStock::paraArticulo(ArticuloTipo::from($this->tipo), $this->articuloId);

        // --- Filtro: Tipo -----------------------------------------------------
        // getAppliedFilterWithValue() devuelve el valor CRUDO del query string:
        // getAppliedFiltersWithValues() llama validate() dentro del closure de
        // array_filter, pero array_filter devuelve los valores originales. Hay
        // que hacer la whitelist a mano.
        $tipos = array_values(array_intersect(
            (array) ($this->getAppliedFilterWithValue('tipo') ?? []),
            MovimientoStockTipo::values()
        ));

        if ($tipos !== []) {
            $query->whereIn('movimientos_stock.tipo', $tipos);
        }

        // --- Filtro: Sucursal -------------------------------------------------
        // Por NOMBRE y no por id: es lo que expone el UNION (las cinco ramas
        // traen s.nombre). Misma whitelist manual que el filtro de tipo y por
        // el mismo motivo.
        $sucursales = array_values(array_intersect(
            (array) ($this->getAppliedFilterWithValue('sucursal') ?? []),
            Sucursal::pluck('nombre')->all()
        ));

        if ($sucursales !== []) {
            $query->whereIn('movimientos_stock.sucursal', $sucursales);
        }

        // --- Filtros: rango de fechas ----------------------------------------
        if ($desde = $this->fechaFiltrada('fecha_desde')) {
            $query->whereDate('movimientos_stock.fecha', '>=', $desde);
        }

        if ($hasta = $this->fechaFiltrada('fecha_hasta')) {
            $query->whereDate('movimientos_stock.fecha', '<=', $hasta);
        }

        // --- Búsqueda ---------------------------------------------------------
        // Ninguna columna está marcada ->searchable() a propósito: con 0
        // columnas searchable, applySearch() es un no-op, y la caja de búsqueda
        // se sigue mostrando (showSearchField() no depende de las columnas).
        $search = trim($this->search);

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $like = '%' . $search . '%';
                $q->where('movimientos_stock.detalle', 'like', $like)
                    ->orWhere('movimientos_stock.tipo', 'like', $like)
                    ->orWhere('movimientos_stock.usuario', 'like', $like)
                    ->orWhere('movimientos_stock.sucursal', 'like', $like)
                    ->orWhere('movimientos_stock.referencia_id', 'like', $like);
            });
        }

        return $query;
    }

    protected function fechaFiltrada(string $key): ?string
    {
        $valor = $this->getAppliedFilterWithValue($key);

        if (! is_string($valor) || $valor === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $valor)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function builder(): Builder
    {
        return $this->scopedQuery();
    }

    /**
     * Totales de TODAS las filas filtradas, no solo de la página visible: el
     * footer nativo recibe $this->getRows, que es el paginador de la página
     * actual (components/table/tr/footer.blade.php).
     *
     * Sumar `cantidad` mezclando entradas y salidas no significa nada, de ahí
     * el desglose con CASE WHEN direccion.
     */
    protected function getTotales(): object
    {
        return $this->totales ??= $this->scopedQuery()
            ->toBase()
            ->selectRaw("
                COALESCE(SUM(CASE WHEN direccion = 'Entrada' THEN cantidad       ELSE 0 END), 0) as unidades_entrada,
                COALESCE(SUM(CASE WHEN direccion = 'Salida'  THEN cantidad       ELSE 0 END), 0) as unidades_salida,
                COALESCE(SUM(CASE WHEN direccion = 'Entrada' THEN subtotal_costo ELSE 0 END), 0) as costo_entrada,
                COALESCE(SUM(CASE WHEN direccion = 'Salida'  THEN subtotal_costo ELSE 0 END), 0) as costo_salida,
                COALESCE(SUM(total_venta), 0)                                                    as total_venta
            ")
            ->first();
    }

    public function filters(): array
    {
        // Sin ->filter(): applyFilters() ignora los filtros sin callback
        // (hasFilterCallback() === false) y los aplicamos en scopedQuery() para
        // que el footer cuadre. Pills, badge de conteo y query string siguen ok.
        //
        // No hay filtro por "Dirección" a propósito. Era función total del tipo
        // (Compra=Entrada, Venta/Reparacion=Salida) y por eso redundante; con
        // Transferencia ya no lo es -- es las dos direcciones a la vez -- pero
        // sigue sin filtro porque filtrar media transferencia descuadraria el
        // pie de la tabla, que es justo lo que este archivo evita.
        return [
            MultiSelectDropdownFilter::make('Tipo', 'tipo')
                ->options(MovimientoStockTipo::toSelectArray()->toArray()),

            MultiSelectDropdownFilter::make('Sucursal', 'sucursal')
                ->options(Sucursal::orderBy('nombre')->pluck('nombre', 'nombre')->toArray()),

            // DateFilter y no DateRangeFilter: DateRangeFilter necesita
            // flatpickr y el layout no carga los assets del paquete.
            DateFilter::make('Desde', 'fecha_desde'),
            DateFilter::make('Hasta', 'fecha_hasta'),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Fecha', 'fecha')
                // Callback en vez de sortable() a secas: sin desempate por id,
                // dos movimientos con la misma fecha pueden saltar de página.
                // applySorting() hace setBuilder(call_user_func(...)) con tipo
                // Builder => el callback DEBE devolver el builder.
                ->sortable(fn(Builder $query, string $direction) => $query
                    ->orderBy('movimientos_stock.fecha', $direction)
                    ->orderBy('movimientos_stock.id', $direction))
                ->format(fn($value) => Carbon::parse($value)->format('d/m/Y'))
                ->footer(fn($rows) => 'TOTALES'),

            Column::make('Tipo', 'tipo')
                ->sortable()
                ->format(function ($value) {
                    $tipo = MovimientoStockTipo::tryFrom($value);

                    return '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold '
                        . ($tipo?->badgeClasses() ?? 'bg-gray-100 text-gray-800') . '">'
                        . e($tipo?->label() ?? $value) . '</span>';
                })
                ->html(),

            Column::make('Documento', 'referencia_id')
                ->sortable()
                ->format(fn($value) => '#' . $value)
                ->collapseOnMobile(),

            Column::make('Detalle', 'detalle')
                ->sortable()
                ->format(fn($value) => $value ?: '—'),

            // La sucursal del MOVIMIENTO, que es la del stock: la de la linea en
            // una venta, la congelada en una reparacion, el origen o el destino
            // en una transferencia.
            Column::make('Sucursal', 'sucursal')
                ->sortable()
                ->format(fn($value) => $value ?: '—'),

            Column::make('Cantidad', 'cantidad')
                ->sortable()
                ->format(function ($value, $row) {
                    $entrada = $row->direccion === 'Entrada';

                    return '<span class="font-semibold '
                        . ($entrada ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400')
                        . '">' . ($entrada ? '+' : '−') . (int) $value . '</span>';
                })
                ->html()
                ->footer(fn($rows) => '<span class="text-green-600 dark:text-green-400">+'
                    . (int) $this->getTotales()->unidades_entrada . '</span> / '
                    . '<span class="text-red-600 dark:text-red-400">−'
                    . (int) $this->getTotales()->unidades_salida . '</span>'),

            Column::make('Costo Unit.', 'costo')
                ->sortable()
                ->format(fn($value) => 'Bs ' . number_format((float) $value, 2))
                ->collapseOnTablet(),

            Column::make('Costo Total', 'subtotal_costo')
                ->sortable()
                ->format(fn($value) => 'Bs ' . number_format((float) $value, 2))
                ->html()
                ->footer(fn($rows) => '<span class="text-green-600 dark:text-green-400">+Bs '
                    . number_format((float) $this->getTotales()->costo_entrada, 2) . '</span><br>'
                    . '<span class="text-red-600 dark:text-red-400">−Bs '
                    . number_format((float) $this->getTotales()->costo_salida, 2) . '</span>'),

            // NULL en compras y reparaciones: productos_reparaciones_repuestos
            // no guarda precio de venta, solo costo.
            Column::make('Precio Unit.', 'precio')
                ->sortable()
                ->format(fn($value) => is_null($value) ? '—' : 'Bs ' . number_format((float) $value, 2))
                ->collapseOnTablet(),

            Column::make('Total Venta', 'total_venta')
                ->sortable()
                ->format(fn($value) => is_null($value) ? '—' : 'Bs ' . number_format((float) $value, 2))
                ->footer(fn($rows) => 'Bs ' . number_format((float) $this->getTotales()->total_venta, 2)),

            // NULL en reparaciones: productos_reparaciones no tiene user_id.
            Column::make('Usuario', 'usuario')
                ->sortable()
                ->format(fn($value) => $value ?: '—')
                ->collapseOnTablet(),
        ];
    }
}
