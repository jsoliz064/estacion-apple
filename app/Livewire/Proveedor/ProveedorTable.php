<?php

namespace App\Livewire\Proveedor;

use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use App\Models\Proveedor;
use Livewire\Attributes\On;


class ProveedorTable extends DataTableComponent
{
    protected $model = Proveedor::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

    public function columns(): array
    {
        return [
            Column::make("Id", "id")
                ->sortable(),
            Column::make("Nombre", "nombre")
                ->sortable()
                ->searchable(),
            // Lo que se le debe (cuentas por pagar), en una subconsulta.
            Column::make("Se le debe")
                ->label(fn($row) => (float) $row->deuda > 0
                    ? '<span class="font-semibold text-amber-700 dark:text-amber-300">Bs ' . number_format((float) $row->deuda, 2) . '</span>'
                    : '<span class="text-gray-400">—</span>')
                ->html(),
            Column::make('Acciones', 'id')
                ->format(function ($value, $row, Column $column) {
                    return view('livewire.proveedor.actions-buttons', [
                        'row' => $row
                    ]);
                }),
        ];
    }
    public function builder(): \Illuminate\Database\Eloquent\Builder
    {
        return Proveedor::query()->addSelect([
            'deuda' => \App\Models\Compra::selectRaw('COALESCE(SUM(saldo), 0)')
                ->whereColumn('compras.proveedor_id', 'proveedores.id')
                ->where('compras.saldo', '>', 0),
        ]);
    }

    #[On('refreshProveedorTable')]
    public function refreshProveedorTable()
    {
        $this->builder();
    }

    public function openProveedorEditModal($id)
    {
        $this->dispatch('openProveedorEditModal', $id);
    }

    public function openProveedorDestroyModal($id)
    {
        $this->dispatch('openProveedorDestroyModal', $id);
    }
}
