<?php

namespace App\Livewire\Sucursal;

use App\Models\Sucursal;
use Livewire\Attributes\On;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class SucursalTable extends DataTableComponent
{
    protected $model = Sucursal::class;

    public function configure(): void
    {
        // Nombre propio: el default del paquete es 'table' para todas las tablas.
        $this->setTableName('sucursales');

        $this->setPrimaryKey('id')
            ->setDefaultSort('nombre', 'asc')
            ->setSearchPlaceholder('Buscar por nombre, dirección o teléfono...')
            ->setEmptyMessage('Todavía no hay sucursales registradas.');
    }

    public function columns(): array
    {
        $vacio = fn($value) => $value !== null && $value !== '' ? e($value) : '<span class="text-gray-400">—</span>';

        return [
            Column::make('Id', 'id')
                ->sortable(),

            Column::make('Nombre', 'nombre')
                ->sortable()
                ->searchable(),

            Column::make('Dirección', 'direccion')
                ->sortable()
                ->searchable()
                ->format($vacio)
                ->html(),

            Column::make('Teléfono', 'telefono')
                ->sortable()
                ->searchable()
                ->format($vacio)
                ->html(),

            Column::make('Estado', 'activa')
                ->sortable()
                ->format(fn($value) => $value
                    ? '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Activa</span>'
                    : '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300">Inactiva</span>')
                ->html(),

            Column::make('Acciones', 'id')
                ->format(fn($value, $row, Column $column) => view('livewire.sucursal.actions-buttons', ['row' => $row])),
        ];
    }

    #[On('refreshSucursalTable')]
    public function refreshSucursalTable()
    {
        $this->builder();
    }

    public function openSucursalEditModal($id)
    {
        $this->dispatch('openSucursalEditModal', $id);
    }

    public function openSucursalDestroyModal($id)
    {
        $this->dispatch('openSucursalDestroyModal', $id);
    }
}
