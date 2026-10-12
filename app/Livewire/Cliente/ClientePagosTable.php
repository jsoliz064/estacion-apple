<?php

namespace App\Livewire\Cliente;

use App\Enums\PagoMomento;
use App\Models\Cliente;
use App\Models\VentaPago;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

/** Los pagos que hizo un cliente: al comprar y en cobros posteriores. */
class ClientePagosTable extends DataTableComponent
{
    protected $model = VentaPago::class;

    public $cliente;

    protected ?float $total = null;

    public function mount($cliente_id): void
    {
        $this->cliente = Cliente::findOrFail($cliente_id);
    }

    public function configure(): void
    {
        $this->setTableName('pagos');

        $this->setPrimaryKey('id')
            ->setDefaultSort('fecha', 'desc')
            ->setSearchDisabled()
            ->setEmptyMessage('Este cliente todavía no hizo ningún pago.');

        $this->setAdditionalSelects(['ventas_pagos.id', 'ventas_pagos.venta_id', 'ventas_pagos.nota', 'ventas_pagos.producto_id',
            'ventas_pagos.moneda', 'ventas_pagos.monto_moneda', 'ventas_pagos.tipo_cambio', 'ventas_pagos.metodo_pago_id']);

        $this->setFooterTrAttributes(fn($rows) => [
            'default' => false,
            'class' => 'bg-gray-100 dark:bg-gray-900 font-bold text-gray-900 dark:text-white',
        ]);
    }

    #[On('pagosActualizados')]
    public function refrescar(): void
    {
        $this->total = null;
    }

    protected function scopedQuery(): Builder
    {
        return VentaPago::query()->whereHas('venta', fn($q) => $q->where('cliente_id', $this->cliente->id));
    }

    public function builder(): Builder
    {
        return $this->scopedQuery()->with(['metodo', 'producto.modelo']);
    }

    public function columns(): array
    {
        return [
            Column::make('Fecha', 'fecha')
                ->sortable()
                ->format(fn($value) => $value?->format('d/m/Y H:i'))
                ->footer(fn($rows) => 'TOTAL'),

            Column::make('Venta', 'venta_id')
                ->sortable()
                ->format(fn($value) => '<a href="' . route('ventas.detalles', $value) . '" class="text-brand-600 hover:underline dark:text-brand-400">#' . (int) $value . '</a>')
                ->html(),

            Column::make('Método', 'metodo.nombre')
                ->sortable()
                ->format(fn($value, $row) => e($row->descripcion())),

            Column::make('Monto', 'monto')
                ->sortable()
                ->format(fn($value) => 'Bs ' . number_format((float) $value, 2))
                ->footer(fn($rows) => 'Bs ' . number_format($this->total ??= round((float) $this->scopedQuery()->sum('monto'), 2), 2)),

            Column::make('Cuándo', 'momento')
                ->format(fn($value) => PagoMomento::labelDe($value instanceof PagoMomento ? $value->value : $value))
                ->collapseOnTablet(),

            Column::make('Recibió', 'user.name')
                ->format(fn($value) => $value ?: '—')
                ->collapseOnTablet(),

            Column::make('')
                ->label(fn($row) => auth()->user()->can('pago.anular') && !$row->esPermuta() && !$row->esSena()
                    ? '<button type="button" wire:click="anular(' . (int) $row->id . ')" class="text-xs text-red-600 hover:underline">Anular</button>'
                    : '')
                ->html(),
        ];
    }

    public function anular($pagoId): void
    {
        abort_unless(auth()->user()->can('pago.anular'), 403);

        $this->dispatch('openPagoAnularModal', (int) $pagoId);
    }
}
