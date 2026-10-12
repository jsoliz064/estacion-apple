<?php

namespace App\Livewire\Proveedor;

use App\Models\CompraPago;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

/** Los pagos hechos a un proveedor. */
class ProveedorPagosTable extends DataTableComponent
{
    protected $model = CompraPago::class;

    public $proveedor;

    public function mount($proveedor_id): void
    {
        $this->proveedor = Proveedor::findOrFail($proveedor_id);
    }

    public function configure(): void
    {
        $this->setTableName('pagos_proveedor');
        $this->setPrimaryKey('id')
            ->setDefaultSort('fecha', 'desc')
            ->setSearchDisabled()
            ->setEmptyMessage('Todavía no hay pagos a este proveedor.');
        $this->setAdditionalSelects(['compras_pagos.id', 'compras_pagos.compra_id', 'compras_pagos.moneda', 'compras_pagos.monto_moneda',
            'compras_pagos.tipo_cambio', 'compras_pagos.al_recibir', 'compras_pagos.metodo_pago_id', 'compras_pagos.nota']);
    }

    #[On('pagosProveedorActualizados')]
    public function refrescar(): void
    {
    }

    public function builder(): Builder
    {
        return CompraPago::query()->with('metodo')->whereHas('compra', fn($q) => $q->where('proveedor_id', $this->proveedor->id));
    }

    public function columns(): array
    {
        return [
            Column::make('Fecha', 'fecha')->sortable()->format(fn($v) => $v?->format('d/m/Y H:i')),
            Column::make('Compra', 'compra_id')
                ->format(fn($v) => '<a href="' . route('compras.detalle', $v) . '" class="text-brand-600 hover:underline dark:text-brand-400">#' . (int) $v . '</a>')
                ->html(),
            Column::make('Método')->label(fn($row) => e($row->descripcion())),
            Column::make('Monto', 'monto')->sortable()->format(fn($v) => 'Bs ' . number_format((float) $v, 2)),
            Column::make('Registró', 'user.name')->format(fn($v) => $v ?: '—')->collapseOnTablet(),
            Column::make('')
                ->label(fn($row) => auth()->user()->can('pago-proveedor.anular')
                    ? '<button type="button" wire:click="anular(' . (int) $row->id . ')" class="text-xs text-red-600 hover:underline">Anular</button>'
                    : '')
                ->html(),
        ];
    }

    public function anular($id): void
    {
        abort_unless(auth()->user()->can('pago-proveedor.anular'), 403);

        $this->dispatch('openPagoProveedorAnularModal', (int) $id);
    }
}
