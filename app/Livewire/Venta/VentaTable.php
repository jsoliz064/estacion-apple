<?php

namespace App\Livewire\Venta;

use App\Enums\LineaTipo;
use App\Models\Venta;
use Carbon\Carbon;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;
use Livewire\Attributes\On;
use Illuminate\Database\Eloquent\Builder;

class VentaTable extends DataTableComponent
{
    protected $model = Venta::class;

    public string $fechaDesde = '';
    public string $fechaHasta = '';
    public array $users = [];
    public array $sucursales = [];

    #[On('filtersUpdated')]
    public function updateTableFilters($filters)
    {
        $this->fechaDesde = $filters['fechaDesde'] ?? '';
        $this->fechaHasta = $filters['fechaHasta'] ?? '';
        $this->users = $filters['users'] ?? [];
        $this->sucursales = $filters['sucursales'] ?? [];

        $this->setBuilder($this->builder());
    }

    public function configure(): void
    {
        $this->setTableName('ventas');
        $this->setPrimaryKey('id')
            ->setDefaultSort('ventas.id', 'desc')
            ->setSearchPlaceholder('Buscar por nº de venta, cliente o vendedor...');
    }

    public function columns(): array
    {
        return [
            Column::make('Nº', 'id')
                ->sortable()
                ->searchable(),
            Column::make('Fecha', 'created_at')
                ->sortable()
                ->format(fn($value) => Carbon::parse($value)->format('d/m/Y H:i')),
            Column::make('Sucursal', 'sucursal.nombre')
                ->sortable()
                ->collapseOnTablet(),
            // La ficha manda; el texto congelado es el respaldo (nombreCliente()).
            // La busqueda mira las dos columnas para encontrar tambien las viejas.
            Column::make('Cliente', 'cliente')
                ->format(fn($value, $row) => $row->nombreCliente() ?: '—')
                ->searchable(fn(Builder $q, $term) => $q
                    ->orWhere('ventas.cliente', 'like', '%' . $term . '%')
                    ->orWhereHas('fichaCliente', fn($c) => $c->where('nombre', 'like', '%' . $term . '%'))),
            Column::make('Equipos', 'id')
                ->label(fn($row) => (int) $row->equipos)
                ->setCustomSlug('equipos'),
            Column::make('Artículos', 'id')
                ->label(fn($row) => (int) $row->articulos)
                ->setCustomSlug('articulos')
                ->collapseOnTablet(),
            Column::make('Descuento', 'descuento')
                ->sortable()
                ->format(fn($value) => 'Bs ' . number_format((float) $value, 2))
                ->collapseOnTablet(),
            Column::make('Total', 'total')
                ->sortable()
                ->format(fn($value) => 'Bs ' . number_format((float) $value, 2)),
            Column::make('Saldo', 'saldo')
                ->sortable()
                ->format(fn($value) => (float) $value > 0
                    ? '<span class="font-semibold text-amber-700 dark:text-amber-300">Bs ' . number_format((float) $value, 2) . '</span>'
                    : '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">Pagada</span>')
                ->html(),
            Column::make('Vendedor', 'user.name')
                ->sortable()
                ->searchable()
                ->collapseOnTablet(),
            Column::make('Acciones', 'id')
                ->format(fn($value, $row) => view('livewire.venta.actions-buttons', ['row' => $row])),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('Cobro', 'cobro')
                ->options(['' => 'Todas', 'credito' => 'A crédito', 'pagada' => 'Pagadas'])
                ->filter(fn(Builder $q, string $valor) => match ($valor) {
                    'credito' => $q->where('ventas.saldo', '>', 0),
                    'pagada' => $q->where('ventas.saldo', '<=', 0),
                    default => $q,
                }),
        ];
    }

    #[On('pagosActualizados')]
    public function refrescarPagos(): void
    {
    }

    public function builder(): Builder
    {
        // La ficha, cargada de una vez: nombreCliente() la consulta por fila sin
        // esto, y son diez filas por pagina.
        return Venta::query()
            ->with('fichaCliente:id,nombre')
            ->withCount([
                'detalles as equipos' => fn($q) => $q->where('tipo', LineaTipo::Producto->value),
            ])
            ->withSum(['detalles as articulos' => fn($q) => $q->where('tipo', '!=', LineaTipo::Producto->value)], 'cantidad')
            ->when($this->fechaDesde, function ($query) {
                $query->where('ventas.created_at', '>=', Carbon::parse($this->fechaDesde)->startOfDay());
            })
            ->when($this->fechaHasta, function ($query) {
                $query->where('ventas.created_at', '<=', Carbon::parse($this->fechaHasta)->endOfDay());
            })
            ->when(!empty($this->users), function ($query) {
                $query->whereIn('ventas.user_id', $this->users);
            })
            ->when(!empty($this->sucursales), function ($query) {
                $query->whereIn('ventas.sucursal_id', $this->sucursales);
            })
            ;
    }

    #[On('refreshVentaTable')]
    public function refreshVentaTable()
    {
        $this->builder();
    }

    public function openVentaDetalleModal($id)
    {
        return redirect(route('ventas.detalles', $id));
    }
}
