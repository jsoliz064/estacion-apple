<?php

namespace App\Livewire\MetodoPago;

use App\Models\MetodoPago;
use Livewire\Attributes\On;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class MetodoPagoTable extends DataTableComponent
{
    protected $model = MetodoPago::class;

    public function configure(): void
    {
        $this->setTableName('metodos_pago');

        $this->setPrimaryKey('id')
            ->setDefaultSort('orden', 'asc')
            ->setSearchPlaceholder('Buscar por nombre...')
            ->setEmptyMessage('Todavía no hay métodos de pago.');
    }

    public function columns(): array
    {
        return [
            Column::make('Orden', 'orden')->sortable(),

            Column::make('Nombre', 'nombre')
                ->sortable()
                ->searchable(),

            Column::make('Estado', 'activo')
                ->sortable()
                ->format(fn($value) => $value
                    ? '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Activo</span>'
                    : '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300">Inactivo</span>')
                ->html(),

            Column::make('Acciones', 'id')
                ->format(fn($value, $row, Column $column) => view('livewire.metodo-pago.actions-buttons', ['row' => $row])),
        ];
    }

    #[On('refreshMetodoPagoTable')]
    public function refreshMetodoPagoTable()
    {
        $this->builder();
    }

    public function openMetodoPagoEditModal($id)
    {
        $this->dispatch('openMetodoPagoFormModal', $id);
    }

    public function openMetodoPagoDestroyModal($id)
    {
        $this->dispatch('openMetodoPagoDestroyModal', $id);
    }
}
