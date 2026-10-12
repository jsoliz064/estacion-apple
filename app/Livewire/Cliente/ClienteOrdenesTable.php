<?php

namespace App\Livewire\Cliente;

use App\Enums\LineaTipo;
use App\Models\Cliente;
use App\Models\Sucursal;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\DateFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\MultiSelectDropdownFilter;

/**
 * Las ventas de un cliente. Antes era un modelo virtual (ClienteOrden, un UNION
 * de ventas y ventas de repuestos); con la venta unificada una orden es una
 * venta, lleve lo que lleve, y la tabla sale directo de `ventas`.
 */
class ClienteOrdenesTable extends DataTableComponent
{
    protected $model = Venta::class;

    public $cliente;

    /** Memo por request de la fila de totales. */
    protected ?object $totales = null;

    public function mount($cliente_id): void
    {
        $this->cliente = Cliente::findOrFail($cliente_id);
    }

    #[On('pagosActualizados')]
    public function refrescar(): void
    {
        $this->totales = null;
    }

    public function configure(): void
    {
        // Nombre propio: el default del paquete es 'table' para TODAS las tablas
        // (query string, id del DOM y evento Alpine de filas colapsadas).
        $this->setTableName('ordenes');

        $this->setPrimaryKey('id')
            ->setDefaultSort('created_at', 'desc')
            ->setSearchPlaceholder('Buscar por nº de venta, vendedor o sucursal...')
            ->setEmptyMessage('Este cliente todavía no tiene ninguna venta registrada.');

        $this->setAdditionalSelects(['ventas.id']);

        $this->setFooterTrAttributes(fn($rows) => [
            'default' => false,
            'class' => 'bg-gray-100 dark:bg-gray-900 font-bold text-gray-900 dark:text-white',
        ]);
    }

    /**
     * Query base compartida por builder() y getTotales().
     *
     * TODO filtro y TODA busqueda se aplican AQUI, nunca con ->filter() en
     * filters() ni con ->searchable() en columns(): applyFilters() y applySearch()
     * tocan solo el builder de las filas, no la query agregada del footer, y los
     * totales quedarian descuadrados respecto a lo que se ve.
     */
    protected function scopedQuery(): Builder
    {
        // Estrictamente por cliente_id: es lo unico que sigue a la persona.
        $query = Venta::query()->where('ventas.cliente_id', $this->cliente->id);

        // getAppliedFilterWithValue() devuelve el valor CRUDO del query string,
        // asi que la whitelist se hace a mano.
        $tipos = array_values(array_intersect(
            (array) ($this->getAppliedFilterWithValue('tipo') ?? []),
            LineaTipo::values(),
        ));

        if ($tipos !== []) {
            $query->whereHas('detalles', fn($q) => $q->whereIn('tipo', $tipos));
        }

        $sucursales = array_values(array_intersect(
            array_map('intval', (array) ($this->getAppliedFilterWithValue('sucursal') ?? [])),
            Sucursal::pluck('id')->all(),
        ));

        if ($sucursales !== []) {
            $query->whereIn('ventas.sucursal_id', $sucursales);
        }

        if ($desde = $this->fechaFiltrada('fecha_desde')) {
            $query->whereDate('ventas.created_at', '>=', $desde);
        }

        if ($hasta = $this->fechaFiltrada('fecha_hasta')) {
            $query->whereDate('ventas.created_at', '<=', $hasta);
        }

        // Ninguna columna lleva ->searchable() a proposito: con 0 columnas
        // searchable applySearch() es un no-op, y la caja se sigue mostrando.
        $search = trim((string) $this->search);

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(fn(Builder $q) => $q
                ->where('ventas.id', ltrim($search, '#'))
                ->orWhereHas('user', fn($u) => $u->where('name', 'like', $like))
                ->orWhereHas('sucursal', fn($s) => $s->where('nombre', 'like', $like)));
        }

        return $query;
    }

    protected function fechaFiltrada(string $key): ?string
    {
        $valor = $this->getAppliedFilterWithValue($key);

        if (!is_string($valor) || $valor === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $valor)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Lo que llevo cada venta, como subconsultas escalares: una columna en el
     * SELECT principal y cero N+1 al pintar.
     */
    public function builder(): Builder
    {
        $contar = fn(string $tipo) => VentaDetalle::query()
            ->selectRaw('COALESCE(SUM(cantidad), 0)')
            ->whereColumn('ventas_detalles.venta_id', 'ventas.id')
            ->whereNull('ventas_detalles.producto_regalo_id')
            ->where('ventas_detalles.tipo', $tipo);

        return $this->scopedQuery()->addSelect([
            'equipos' => $contar(LineaTipo::Producto->value),
            'repuestos' => $contar(LineaTipo::Repuesto->value),
            'accesorios' => $contar(LineaTipo::Accesorio->value),
        ]);
    }

    /**
     * Totales de TODAS las filas filtradas, no solo de la pagina visible: el
     * footer nativo recibe $this->getRows, que es el paginador de la pagina actual.
     */
    protected function getTotales(): object
    {
        return $this->totales ??= $this->scopedQuery()
            ->toBase()
            ->selectRaw('COUNT(*) as ordenes, COALESCE(SUM(total), 0) as total, COALESCE(SUM(saldo), 0) as saldo')
            ->first();
    }

    public function filters(): array
    {
        // Sin ->filter(): applyFilters() ignora los filtros sin callback y los
        // aplicamos en scopedQuery() para que el footer cuadre.
        return [
            MultiSelectDropdownFilter::make('Contiene', 'tipo')
                ->options(LineaTipo::toSelectArray()->toArray()),

            MultiSelectDropdownFilter::make('Sucursal', 'sucursal')
                ->options(Sucursal::orderBy('nombre')->pluck('nombre', 'id')->toArray()),

            // DateFilter y no DateRangeFilter: este ultimo necesita flatpickr y el
            // layout no carga los assets del paquete.
            DateFilter::make('Desde', 'fecha_desde'),
            DateFilter::make('Hasta', 'fecha_hasta'),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Fecha', 'created_at')
                // Callback y no sortable() a secas: sin desempate por id, dos
                // ventas del mismo segundo pueden saltar de pagina.
                ->sortable(fn(Builder $query, string $direction) => $query
                    ->orderBy('ventas.created_at', $direction)
                    ->orderBy('ventas.id', $direction))
                ->format(fn($value) => Carbon::parse($value)->format('d/m/Y H:i'))
                ->footer(fn($rows) => 'TOTALES'),

            Column::make('Venta', 'id')
                ->sortable()
                ->format(fn($value) => '<a href="' . route('ventas.detalles', $value) . '" '
                    . 'class="font-semibold text-brand-600 hover:underline dark:text-brand-400">Venta #' . (int) $value . '</a>')
                ->html()
                ->footer(fn($rows) => (int) $this->getTotales()->ordenes . ' venta(s)'),

            // Label: los conteos viven como alias del SELECT, y selectFields()
            // cualificaria un campo normal a ventas.equipos.
            Column::make('Contenido')
                ->label(function ($row) {
                    $partes = array_filter([
                        (int) $row->equipos ? $row->equipos . ' equipo(s)' : null,
                        (int) $row->repuestos ? $row->repuestos . ' repuesto(s)' : null,
                        (int) $row->accesorios ? $row->accesorios . ' accesorio(s)' : null,
                    ]);

                    return $partes ? implode(' · ', $partes) : '—';
                }),

            Column::make('Total (Bs)', 'total')
                ->sortable()
                ->format(fn($value) => 'Bs. ' . number_format((float) $value, 2))
                ->footer(fn($rows) => 'Bs. ' . number_format((float) $this->getTotales()->total, 2)),

            Column::make('Saldo (Bs)', 'saldo')
                ->sortable()
                ->format(fn($value) => (float) $value > 0
                    ? '<span class="font-semibold text-amber-700 dark:text-amber-300">Bs. ' . number_format((float) $value, 2) . '</span>'
                    : '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">Pagada</span>')
                ->html()
                ->footer(fn($rows) => 'Bs. ' . number_format((float) $this->getTotales()->saldo, 2)),

            Column::make('Vendedor', 'user.name')
                ->sortable()
                ->format(fn($value) => $value ?: '—')
                ->collapseOnTablet(),

            Column::make('Sucursal', 'sucursal.nombre')
                ->sortable()
                ->format(fn($value) => $value ?: '—')
                ->collapseOnTablet(),
        ];
    }
}
