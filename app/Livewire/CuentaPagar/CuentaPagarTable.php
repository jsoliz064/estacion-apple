<?php

namespace App\Livewire\CuentaPagar;

use App\Models\Compra;
use App\Models\Proveedor;
use App\Models\Sucursal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\MultiSelectDropdownFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;

/**
 * Lo que se le debe a los proveedores: las compras con saldo (docs/06). De la
 * mas antigua a la mas nueva. Como CobranzaTable, todo filtro va en
 * scopedQuery() para que el pie sume todas las filas filtradas.
 */
class CuentaPagarTable extends DataTableComponent
{
    protected $model = Compra::class;

    protected ?object $totales = null;

    public function configure(): void
    {
        $this->setTableName('cuentas_pagar');

        $this->setPrimaryKey('id')
            ->setDefaultSort('fecha', 'asc')
            ->setSearchPlaceholder('Buscar por proveedor o nº de compra...')
            ->setEmptyMessage('No se le debe nada a ningún proveedor.');

        $this->setAdditionalSelects(['compras.id', 'compras.proveedor_id']);

        $this->setFooterTrAttributes(fn($rows) => [
            'default' => false,
            'class' => 'bg-gray-100 dark:bg-gray-900 font-bold text-gray-900 dark:text-white',
        ]);
    }

    #[On('pagosProveedorActualizados')]
    public function refrescar(): void
    {
        $this->totales = null;
    }

    protected function scopedQuery(): Builder
    {
        $query = Compra::query()->conSaldo();

        $proveedores = array_values(array_intersect(
            array_map('intval', (array) ($this->getAppliedFilterWithValue('proveedor') ?? [])),
            Proveedor::pluck('id')->all(),
        ));
        if ($proveedores !== []) {
            $query->whereIn('compras.proveedor_id', $proveedores);
        }

        $sucursales = array_values(array_intersect(
            array_map('intval', (array) ($this->getAppliedFilterWithValue('sucursal') ?? [])),
            Sucursal::pluck('id')->all(),
        ));
        if ($sucursales !== []) {
            $query->whereIn('compras.sucursal_id', $sucursales);
        }

        $dias = (int) ($this->getAppliedFilterWithValue('antiguedad') ?? 0);
        if (in_array($dias, [30, 60, 90], true)) {
            $query->where('compras.fecha', '<=', now()->subDays($dias)->toDateString());
        }

        $search = trim((string) $this->search);
        if ($search !== '') {
            $query->where(fn(Builder $q) => $q
                ->where('compras.id', ltrim($search, '#'))
                ->orWhereHas('proveedor', fn($p) => $p->where('nombre', 'like', '%' . addcslashes($search, '%_\\') . '%')));
        }

        return $query;
    }

    public function builder(): Builder
    {
        return $this->scopedQuery()->with('proveedor:id,nombre');
    }

    protected function getTotales(): object
    {
        return $this->totales ??= $this->scopedQuery()
            ->toBase()
            ->selectRaw('COUNT(*) as compras, COALESCE(SUM(saldo), 0) as saldo')
            ->first();
    }

    public function filters(): array
    {
        return [
            MultiSelectDropdownFilter::make('Proveedor', 'proveedor')
                ->options(Proveedor::orderBy('nombre')->pluck('nombre', 'id')->toArray()),
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
            Column::make('Fecha', 'fecha')
                ->sortable(fn(Builder $query, string $direction) => $query->orderBy('compras.fecha', $direction)->orderBy('compras.id', $direction))
                ->format(fn($value) => Carbon::parse($value)->format('d/m/Y'))
                ->footer(fn($rows) => 'TOTAL'),

            Column::make('Días')
                ->label(fn($row) => (int) Carbon::parse($row->fecha)->startOfDay()->diffInDays(now()->startOfDay())),

            Column::make('Proveedor')
                ->label(fn($row) => auth()->user()->can('proveedor.historial') && $row->proveedor
                    ? '<a href="' . route('proveedores.historial', $row->proveedor_id) . '" class="font-semibold text-brand-600 hover:underline dark:text-brand-400">' . e($row->proveedor->nombre) . '</a>'
                    : e($row->proveedor?->nombre ?? '—'))
                ->html(),

            Column::make('Compra', 'id')
                ->sortable()
                ->format(fn($value) => '<a href="' . route('compras.detalle', $value) . '" class="text-brand-600 hover:underline dark:text-brand-400">#' . (int) $value . '</a>')
                ->html()
                ->footer(fn($rows) => (int) $this->getTotales()->compras . ' compra(s)'),

            Column::make('Total', 'total')->sortable()->format($bs)->collapseOnTablet(),
            Column::make('Pagado', 'pagado')->sortable()->format($bs)->collapseOnTablet(),

            Column::make('Saldo', 'saldo')
                ->sortable()
                ->format(fn($value) => '<span class="font-semibold text-amber-700 dark:text-amber-300">Bs ' . number_format((float) $value, 2) . '</span>')
                ->html()
                ->footer(fn($rows) => 'Bs ' . number_format((float) $this->getTotales()->saldo, 2)),

            Column::make('Sucursal', 'sucursal.nombre')
                ->sortable()
                ->format(fn($value) => $value ?: '—')
                ->collapseOnTablet(),

            Column::make('')
                ->label(fn($row) => auth()->user()->can('pago-proveedor.create')
                    ? '<button type="button" wire:click="pagar(' . (int) $row->proveedor_id . ', ' . (int) $row->id . ')" '
                        . 'class="px-3 py-1 rounded-md bg-brand-600 text-white text-xs font-semibold hover:bg-brand-700">Pagar</button>'
                    : '')
                ->html(),
        ];
    }

    public function pagar($proveedorId, $compraId): void
    {
        abort_unless(auth()->user()->can('pago-proveedor.create'), 403);

        $this->dispatch('openPagoProveedorModal', proveedorId: (int) $proveedorId, compraId: (int) $compraId);
    }
}
