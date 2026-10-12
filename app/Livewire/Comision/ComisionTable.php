<?php

namespace App\Livewire\Comision;

use App\Enums\ComisionEstado;
use App\Models\Comision;
use App\Models\Tecnicos;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\DateFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;

/**
 * El detalle de las comisiones: es tambien el reporte de comisiones del periodo
 * (docs/05, entregable 6). Las de monto 0 (vendedor sin %, ganancia negativa)
 * no se listan: existen solo para congelar el %.
 *
 * Con `usuarioId` (Mis comisiones, ficha del usuario) muestra solo lo de esa
 * persona: sus ventas y las reparaciones del tecnico vinculado a su usuario.
 * Va por parametro de montaje y Locked, nunca por evento.
 *
 * Todo filtro va en scopedQuery() para que el pie sume todas las filas
 * filtradas, no solo la pagina.
 */
class ComisionTable extends DataTableComponent
{
    protected $model = Comision::class;

    #[Locked]
    public ?int $usuarioId = null;

    protected ?object $totales = null;

    public function mount(?int $usuarioId = null): void
    {
        $this->usuarioId = $usuarioId;
    }

    public function configure(): void
    {
        $this->setTableName('comisiones');

        $this->setPrimaryKey('id')
            ->setDefaultSort('id', 'desc')
            ->setSearchPlaceholder('Buscar por referencia o nº de venta...')
            ->setEmptyMessage('No hay comisiones con estos filtros.');

        $this->setAdditionalSelects([
            'comisiones.id', 'comisiones.user_id', 'comisiones.tecnico_id', 'comisiones.venta_id',
            'comisiones.producto_reparacion_id', 'comisiones.liquidacion_id', 'comisiones.origen',
            // Una columna con label() no selecciona su campo.
            'comisiones.referencia',
        ]);

        $this->setFooterTrAttributes(fn($rows) => [
            'default' => false,
            'class' => 'bg-gray-100 dark:bg-gray-900 font-bold text-gray-900 dark:text-white',
        ]);
    }

    #[On('comisionesActualizadas')]
    public function refrescar(): void
    {
        $this->totales = null;
    }

    /** "Detalle" de la pantalla de comisiones: filtra por esa persona. */
    #[On('comisionPersona')]
    public function filtrarPersona(string $clave): void
    {
        if ($this->usuarioId === null) {
            $this->setFilter('persona', $clave);
            $this->totales = null;
        }
    }

    protected function scopedQuery(): Builder
    {
        $query = Comision::query()->where('comisiones.monto', '>', 0);

        if ($this->usuarioId !== null) {
            $query->deUsuario(User::findOrFail($this->usuarioId));
        } elseif ($persona = $this->getAppliedFilterWithValue('persona')) {
            $query->deBeneficiario((string) $persona);
        }

        match ($this->getAppliedFilterWithValue('tipo')) {
            'vendedor' => $query->whereNotNull('comisiones.user_id'),
            'tecnico' => $query->whereNotNull('comisiones.tecnico_id'),
            default => null,
        };

        if ($estado = $this->getAppliedFilterWithValue('estado')) {
            $query->conEstado((string) $estado);
        }

        // La fecha de una comision es cuando se gano; la pendiente, cuando nacio.
        if ($desde = $this->fecha('desde')) {
            $query->whereRaw('DATE(COALESCE(comisiones.ganada_at, comisiones.created_at)) >= ?', [$desde]);
        }
        if ($hasta = $this->fecha('hasta')) {
            $query->whereRaw('DATE(COALESCE(comisiones.ganada_at, comisiones.created_at)) <= ?', [$hasta]);
        }

        $search = trim((string) $this->search);
        if ($search !== '') {
            $query->where(fn(Builder $q) => $q
                ->where('comisiones.venta_id', ltrim($search, '#'))
                ->orWhere('comisiones.referencia', 'like', '%' . addcslashes($search, '%_\\') . '%'));
        }

        return $query;
    }

    private function fecha(string $filtro): ?string
    {
        $valor = $this->getAppliedFilterWithValue($filtro);

        return $valor ? rescue(fn() => Carbon::parse($valor)->toDateString(), null, false) : null;
    }

    public function builder(): Builder
    {
        return $this->scopedQuery()->with(['user:id,name', 'tecnico:id,nombre', 'reparacion:id,producto_id']);
    }

    protected function getTotales(): object
    {
        return $this->totales ??= $this->scopedQuery()
            ->toBase()
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(comisiones.monto), 0) as monto')
            ->first();
    }

    public function filters(): array
    {
        $filtros = [];

        if ($this->usuarioId === null) {
            $personas = User::whereIn('id', Comision::whereNotNull('user_id')->select('user_id'))
                ->orderBy('name')->pluck('name', 'id')
                ->mapWithKeys(fn($n, $id) => ['U-' . $id => $n . ' (vendedor)'])
                ->union(Tecnicos::whereIn('id', Comision::whereNotNull('tecnico_id')->select('tecnico_id'))
                    ->orderBy('nombre')->pluck('nombre', 'id')
                    ->mapWithKeys(fn($n, $id) => ['T-' . $id => $n . ' (técnico)']));

            $filtros[] = SelectFilter::make('Persona', 'persona')
                ->options(['' => 'Todas'] + $personas->all());
            $filtros[] = SelectFilter::make('Tipo', 'tipo')
                ->options(['' => 'Todos', 'vendedor' => 'Vendedores', 'tecnico' => 'Técnicos']);
        }

        $filtros[] = SelectFilter::make('Estado', 'estado')
            ->options(['' => 'Todos'] + array_combine(ComisionEstado::values(), ComisionEstado::values()));
        $filtros[] = DateFilter::make('Desde', 'desde');
        $filtros[] = DateFilter::make('Hasta', 'hasta');

        return $filtros;
    }

    public function columns(): array
    {
        $bs = fn($value) => 'Bs ' . number_format((float) $value, 2);

        return [
            Column::make('Fecha', 'ganada_at')
                ->sortable()
                ->format(fn($value) => $value ? Carbon::parse($value)->format('d/m/Y') : '<span class="text-gray-400">—</span>')
                ->html()
                ->footer(fn($rows) => 'TOTAL'),

            Column::make('Persona')
                ->label(fn($row) => e($row->beneficiarioNombre())
                    . '<span class="block text-xs text-gray-500">' . ($row->user_id ? 'Vendedor' : 'Técnico') . '</span>')
                ->html(),

            Column::make('Origen')
                ->label(fn($row) => $this->origen($row))
                ->html()
                ->footer(fn($rows) => (int) $this->getTotales()->cantidad . ' comisión(es)'),

            Column::make('Base', 'base')
                ->sortable()
                ->format($bs)
                ->collapseOnTablet(),

            Column::make('%', 'porcentaje')
                ->format(fn($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.') . ' %')
                ->collapseOnTablet(),

            Column::make('Comisión', 'monto')
                ->sortable()
                ->format(fn($value) => '<span class="font-semibold">Bs ' . number_format((float) $value, 2) . '</span>')
                ->html()
                ->footer(fn($rows) => 'Bs ' . number_format((float) $this->getTotales()->monto, 2)),

            Column::make('Estado')
                ->label(fn($row) => $row->estado()->badge()
                    . ($row->liquidacion_id ? '<span class="block text-xs text-gray-500">Liquidación #' . (int) $row->liquidacion_id . '</span>' : ''))
                ->html(),
        ];
    }

    /** La referencia, con enlace a la venta o al equipo si se puede abrir. */
    private function origen(Comision $row): string
    {
        $texto = e($row->referencia);
        $user = auth()->user();

        if ($row->venta_id && $user->can('venta.detalle')) {
            return '<a href="' . route('ventas.detalles', $row->venta_id) . '" class="text-brand-600 hover:underline dark:text-brand-400">' . $texto . '</a>';
        }

        if ($row->reparacion?->producto_id && $user->can('producto.historial')) {
            return '<a href="' . route('productos.historial', $row->reparacion->producto_id) . '" class="text-brand-600 hover:underline dark:text-brand-400">' . $texto . '</a>';
        }

        if (!$row->venta_id && !$row->producto_reparacion_id) {
            $texto .= ' <span class="text-xs text-rose-600">(anulada)</span>';
        }

        return $texto;
    }
}
