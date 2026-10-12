<?php

namespace App\Livewire\Compra;

use App\Enums\CompraEstado;
use App\Enums\LineaTipo;
use App\Models\Compra;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;

/** El listado de compras: una por documento, con equipos y articulos juntos. */
class CompraTable extends DataTableComponent
{
    protected $model = Compra::class;

    public function configure(): void
    {
        $this->setTableName('compras');

        $this->setPrimaryKey('id')
            ->setDefaultSort('compras.id', 'desc')
            ->setSearchPlaceholder('Buscar por proveedor o nº de compra...')
            ->setEmptyMessage('Todavía no hay compras registradas.');

        // Los label() no seleccionan su campo: estado() y badgePago() los leen.
        $this->setAdditionalSelects(['compras.finalizada_at', 'compras.pagado', 'compras.pagada_at']);
    }

    public function builder(): Builder
    {
        // Los conteos como subconsulta y no $row->detalles->count() en el format:
        // eso seria una consulta por fila.
        return Compra::query()
            ->conConteoReclamos()
            ->withCount([
                'detalles as equipos' => fn($q) => $q->where('tipo', LineaTipo::Producto->value),
                'detalles as articulos' => fn($q) => $q->where('tipo', '!=', LineaTipo::Producto->value),
            ]);
    }

    public function columns(): array
    {
        return [
            Column::make('Nº', 'id')
                ->sortable()
                ->searchable(),
            Column::make('Fecha', 'fecha')
                ->sortable()
                ->format(fn($value) => Carbon::parse($value)->format('d/m/Y')),
            Column::make('Proveedor', 'proveedor.nombre')
                ->sortable()
                ->searchable(),
            Column::make('Sucursal', 'sucursal.nombre')
                ->sortable()
                ->collapseOnTablet(),
            Column::make('Equipos', 'id')
                ->label(fn($row) => (int) $row->equipos)
                ->setCustomSlug('equipos'),
            Column::make('Artículos', 'id')
                ->label(fn($row) => (int) $row->articulos)
                ->setCustomSlug('articulos'),
            Column::make('Total', 'total')
                ->sortable()
                ->format(fn($value) => 'Bs ' . number_format((float) $value, 2)),
            Column::make('Saldo', 'saldo')
                ->sortable()
                ->format(fn($value) => (float) $value > 0
                    ? '<span class="font-semibold text-amber-700 dark:text-amber-300">Bs ' . number_format((float) $value, 2) . '</span>'
                    : '<span class="text-gray-400">—</span>')
                ->html(),
            Column::make('Estado')
                ->label(fn($row) => $row->estado()->badge())
                ->html(),
            Column::make('Pago')
                ->label(fn($row) => $row->badgePago())
                ->html()
                ->collapseOnTablet(),
            Column::make('Acciones', 'id')
                ->format(fn($value, $row) => view('livewire.compra.actions-buttons', ['row' => $row])),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('Estado', 'estado')
                ->options(['' => 'Todos'] + collect(CompraEstado::cases())->mapWithKeys(fn($e) => [$e->value => $e->value])->all())
                ->filter(fn(Builder $q, string $v) => ($e = CompraEstado::tryFrom($v)) ? $q->conEstado($e) : $q),
            SelectFilter::make('Pago', 'pago')
                ->options(['' => 'Todas', 'saldo' => 'Con saldo (por pagar)', 'pagada' => 'Pagadas'])
                ->filter(fn(Builder $q, string $v) => match ($v) {
                    'saldo' => $q->where('compras.saldo', '>', 0),
                    'pagada' => $q->where('compras.saldo', '<=', 0),
                    default => $q,
                }),
        ];
    }

    #[On('refreshCompraTable')]
    #[On('pagosProveedorActualizados')]
    public function refreshCompraTable()
    {
        $this->builder();
    }

    public function verCompra($id)
    {
        return redirect()->route('compras.detalle', $id);
    }

    public function editarCompra($id)
    {
        return redirect()->route('compras.editar', $id);
    }

    public function openCompraDestroyModal($id)
    {
        $this->dispatch('openCompraDestroyModal', $id);
    }
}
