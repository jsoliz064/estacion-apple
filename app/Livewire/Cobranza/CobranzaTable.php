<?php

namespace App\Livewire\Cobranza;

use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\MultiSelectDropdownFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;

/**
 * Lo que esta por cobrar: las ventas con saldo. La herramienta diaria de quien
 * cobra (docs/04): de la mas antigua a la mas nueva por defecto, ordenable por
 * saldo, filtrable por cliente, vendedor, sucursal y antiguedad.
 *
 * Como en ClienteOrdenesTable, todo filtro y toda busqueda van en
 * scopedQuery(): el pie suma TODAS las filas filtradas, no solo la pagina.
 */
class CobranzaTable extends DataTableComponent
{
    protected $model = Venta::class;

    protected ?object $totales = null;

    public function configure(): void
    {
        $this->setTableName('cobranzas');

        $this->setPrimaryKey('id')
            ->setDefaultSort('created_at', 'asc')
            ->setSearchPlaceholder('Buscar por cliente, CI o nº de venta...')
            ->setEmptyMessage('No hay nada por cobrar.');

        $this->setAdditionalSelects(['ventas.id', 'ventas.cliente_id', 'ventas.cliente']);

        $this->setFooterTrAttributes(fn($rows) => [
            'default' => false,
            'class' => 'bg-gray-100 dark:bg-gray-900 font-bold text-gray-900 dark:text-white',
        ]);
    }

    #[On('pagosActualizados')]
    public function refrescar(): void
    {
        $this->totales = null;
    }

    protected function scopedQuery(): Builder
    {
        $query = Venta::query()->conSaldo();

        $vendedores = array_values(array_intersect(
            array_map('intval', (array) ($this->getAppliedFilterWithValue('vendedor') ?? [])),
            User::pluck('id')->all(),
        ));
        if ($vendedores !== []) {
            $query->whereIn('ventas.user_id', $vendedores);
        }

        $sucursales = array_values(array_intersect(
            array_map('intval', (array) ($this->getAppliedFilterWithValue('sucursal') ?? [])),
            Sucursal::pluck('id')->all(),
        ));
        if ($sucursales !== []) {
            $query->whereIn('ventas.sucursal_id', $sucursales);
        }

        $dias = (int) ($this->getAppliedFilterWithValue('antiguedad') ?? 0);
        if (in_array($dias, [30, 60, 90], true)) {
            $query->where('ventas.created_at', '<=', now()->subDays($dias));
        }

        $search = trim((string) $this->search);
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $query->where(fn(Builder $q) => $q
                ->where('ventas.id', ltrim($search, '#'))
                ->orWhereHas('fichaCliente', fn($c) => $c->where('nombre', 'like', $like)->orWhere('ci', $search)));
        }

        return $query;
    }

    public function builder(): Builder
    {
        return $this->scopedQuery()->with('fichaCliente:id,nombre,ci,telefono');
    }

    protected function getTotales(): object
    {
        return $this->totales ??= $this->scopedQuery()
            ->toBase()
            ->selectRaw('COUNT(*) as ventas, COALESCE(SUM(saldo), 0) as saldo, COALESCE(SUM(total), 0) as total')
            ->first();
    }

    public function filters(): array
    {
        // Sin ->filter(): se aplican en scopedQuery() para que el pie cuadre.
        return [
            MultiSelectDropdownFilter::make('Vendedor', 'vendedor')
                ->options(User::orderBy('name')->pluck('name', 'id')->toArray()),
            MultiSelectDropdownFilter::make('Sucursal', 'sucursal')
                ->options(Sucursal::orderBy('nombre')->pluck('nombre', 'id')->toArray()),
            SelectFilter::make('Antigüedad', 'antiguedad')
                ->options(['' => 'Todas', '30' => 'Más de 30 días', '60' => 'Más de 60 días', '90' => 'Más de 90 días']),
        ];
    }

    public function columns(): array
    {
        $bs = fn($value) => 'Bs ' . number_format((float) $value, 2);

        return [
            Column::make('Fecha', 'created_at')
                ->sortable(fn(Builder $query, string $direction) => $query
                    ->orderBy('ventas.created_at', $direction)
                    ->orderBy('ventas.id', $direction))
                ->format(fn($value) => Carbon::parse($value)->format('d/m/Y'))
                ->footer(fn($rows) => 'TOTAL'),

            Column::make('Días')
                ->label(fn($row) => (int) Carbon::parse($row->created_at)->startOfDay()->diffInDays(now()->startOfDay())),

            Column::make('Cliente')
                ->label(function ($row) {
                    $ficha = $row->fichaCliente;
                    $nombre = e($ficha?->nombre ?? $row->cliente ?? '—');

                    return $ficha
                        ? '<a href="' . route('clientes.historial', $ficha->id) . '" class="font-semibold text-brand-600 hover:underline dark:text-brand-400">' . $nombre . '</a>'
                            . ($ficha->telefono ? '<span class="block text-xs text-gray-500">' . e($ficha->telefono) . '</span>' : '')
                        : $nombre;
                })
                ->html(),

            Column::make('Venta', 'id')
                ->sortable()
                ->format(fn($value) => '<a href="' . route('ventas.detalles', $value) . '" class="text-brand-600 hover:underline dark:text-brand-400">#' . (int) $value . '</a>')
                ->html()
                ->footer(fn($rows) => (int) $this->getTotales()->ventas . ' venta(s)'),

            Column::make('Total', 'total')
                ->sortable()
                ->format($bs)
                ->collapseOnTablet(),

            Column::make('Pagado', 'pagado')
                ->sortable()
                ->format($bs)
                ->collapseOnTablet(),

            Column::make('Saldo', 'saldo')
                ->sortable()
                ->format(fn($value) => '<span class="font-semibold text-amber-700 dark:text-amber-300">Bs ' . number_format((float) $value, 2) . '</span>')
                ->html()
                ->footer(fn($rows) => 'Bs ' . number_format((float) $this->getTotales()->saldo, 2)),

            Column::make('Vendedor', 'user.name')
                ->sortable()
                ->format(fn($value) => $value ?: '—')
                ->collapseOnTablet(),

            Column::make('Sucursal', 'sucursal.nombre')
                ->sortable()
                ->format(fn($value) => $value ?: '—')
                ->collapseOnTablet(),

            Column::make('')
                ->label(fn($row) => auth()->user()->can('pago.create') && $row->cliente_id
                    ? '<button type="button" wire:click="cobrar(' . (int) $row->cliente_id . ', ' . (int) $row->id . ')" '
                        . 'class="px-3 py-1 rounded-md bg-brand-600 text-white text-xs font-semibold hover:bg-brand-700">Cobrar</button>'
                    : '')
                ->html(),
        ];
    }

    public function cobrar($clienteId, $ventaId): void
    {
        abort_unless(auth()->user()->can('pago.create'), 403);

        $this->dispatch('openCobroModal', clienteId: (int) $clienteId, ventaId: (int) $ventaId);
    }
}
