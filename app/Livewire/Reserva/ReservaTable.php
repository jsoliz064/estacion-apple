<?php

namespace App\Livewire\Reserva;

use App\Enums\ReservaEstado;
use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;

class ReservaTable extends DataTableComponent
{
    protected $model = Reserva::class;

    /** Boton de camara en la busqueda (vendor/livewire-tables/.../search-field). */
    public bool $buscarConEscaner = true;

    public function configure(): void
    {
        $this->setTableName('reservas');

        $this->setPrimaryKey('id')
            ->setDefaultSort('created_at', 'asc')
            ->setSearchPlaceholder('Buscar por IMEI, cliente o CI...')
            ->setEmptyMessage('No hay reservas.');

        $this->setAdditionalSelects(['reservas.id', 'reservas.producto_id', 'reservas.cliente_id', 'reservas.estado', 'reservas.sena_destino', 'reservas.venta_id']);
    }

    #[On('reservasActualizadas')]
    public function refrescar(): void
    {
    }

    public function builder(): Builder
    {
        $search = trim((string) $this->search);

        return Reserva::query()
            ->with(['producto.modelo', 'cliente', 'metodo', 'user'])
            ->when($search !== '', fn($q) => $q->where(fn($w) => $w
                ->whereHas('producto', fn($p) => $p->where('imei', 'like', '%' . addcslashes($search, '%_\\') . '%')->orWhere('sku', $search))
                ->orWhereHas('cliente', fn($c) => $c->where('nombre', 'like', '%' . addcslashes($search, '%_\\') . '%')->orWhere('ci', $search))));
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('Estado', 'estado')
                ->options(['' => 'Todas'] + array_combine(ReservaEstado::values(), ReservaEstado::values()))
                ->filter(fn(Builder $q, string $valor) => $valor !== '' ? $q->where('reservas.estado', $valor) : $q)
                ->setFilterDefaultValue(ReservaEstado::Activa->value),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Fecha', 'created_at')
                ->sortable()
                ->format(fn($value) => Carbon::parse($value)->format('d/m/Y')),

            Column::make('Días')
                ->label(fn($row) => $row->estado === ReservaEstado::Activa
                    ? (int) Carbon::parse($row->created_at)->startOfDay()->diffInDays(now()->startOfDay())
                    : '—'),

            Column::make('Equipo')
                ->label(fn($row) => e(trim(($row->producto?->modelo?->nombre ?? 'Equipo') . ' ' . $row->producto?->almacenamiento . ' ' . $row->producto?->color))
                    . '<span class="block text-xs font-mono text-gray-500">IMEI ' . e($row->producto?->imei) . '</span>')
                ->html(),

            Column::make('Cliente')
                ->label(fn($row) => $row->cliente
                    ? '<a href="' . route('clientes.historial', $row->cliente_id) . '" class="text-brand-600 hover:underline dark:text-brand-400">' . e($row->cliente->nombre) . '</a>'
                    : '—')
                ->html(),

            Column::make('Seña', 'sena')
                ->sortable()
                ->format(fn($value, $row) => 'Bs ' . number_format((float) $value, 2) . '<span class="block text-xs text-gray-500">' . e($row->metodo?->nombre) . '</span>')
                ->html(),

            Column::make('Estado', 'estado')
                ->format(function ($value, $row) {
                    $estado = $row->estado;
                    $html = '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold ' . $estado->badgeClasses() . '">' . e($estado->label()) . '</span>';

                    if ($row->sena_destino) {
                        $html .= '<span class="block text-xs text-gray-500">' . e($row->sena_destino->label()) . '</span>';
                    }
                    if ($row->venta_id) {
                        $html .= '<a href="' . route('ventas.detalles', $row->venta_id) . '" class="block text-xs text-brand-600 hover:underline">Venta #' . (int) $row->venta_id . '</a>';
                    }

                    return $html;
                })
                ->html(),

            Column::make('Vendedor', 'user.name')
                ->format(fn($value) => $value ?: '—')
                ->collapseOnTablet(),

            Column::make('')
                ->label(function ($row) {
                    if ($row->estado !== ReservaEstado::Activa) {
                        return '';
                    }

                    $botones = '';
                    if (auth()->user()->can('venta.create')) {
                        $botones .= '<a href="' . route('ventas.crear', ['reserva' => $row->id]) . '" class="px-3 py-1 rounded-md bg-brand-600 text-white text-xs font-semibold hover:bg-brand-700">Concretar</a> ';
                    }
                    if (auth()->user()->can('reserva.cancelar')) {
                        $botones .= '<button type="button" wire:click="cancelar(' . (int) $row->id . ')" class="px-3 py-1 rounded-md border border-red-300 text-red-700 text-xs font-semibold hover:bg-red-50">Cancelar</button>';
                    }

                    return '<div class="flex flex-wrap gap-1">' . $botones . '</div>';
                })
                ->html(),
        ];
    }

    public function cancelar($id): void
    {
        abort_unless(auth()->user()->can('reserva.cancelar'), 403);

        $this->dispatch('openReservaCancelarModal', (int) $id);
    }
}
