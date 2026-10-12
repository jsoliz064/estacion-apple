<?php

namespace App\Livewire\Cliente;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class ClienteTable extends DataTableComponent
{
    protected $model = Cliente::class;

    public function configure(): void
    {
        // Nombre propio: el default del paquete es 'table' para todas las tablas
        // (query string, id del DOM y evento Alpine de filas colapsadas).
        $this->setTableName('clientes');

        $this->setPrimaryKey('id')
            ->setDefaultSort('nombre', 'asc')
            ->setSearchPlaceholder('Buscar por nombre, CI, teléfono o correo...')
            ->setEmptyMessage('Todavía no hay clientes registrados.');
    }

    public function builder(): Builder
    {
        // El conteo como subconsulta y no con $row->ventas()->count() en el
        // format: eso seria una consulta por fila. Una venta es una orden, lleve
        // equipos, repuestos o accesorios.
        return Cliente::query()
            ->withCount('ventas as ordenes');
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id')
                ->sortable(),

            Column::make('Nombre', 'nombre')
                ->sortable()
                ->searchable(),

            Column::make('CI', 'ci')
                ->sortable()
                ->searchable()
                ->format(fn($value) => $value ?: '<span class="text-gray-400">—</span>')
                ->html(),

            Column::make('Teléfono', 'telefono')
                ->sortable()
                ->searchable()
                ->format(fn($value) => $value ?: '<span class="text-gray-400">—</span>')
                ->html(),

            Column::make('Correo', 'correo')
                ->sortable()
                ->searchable()
                ->format(fn($value) => $value ?: '<span class="text-gray-400">—</span>')
                ->html(),

            // La cifra del withCount. La clave es 'id' porque el paquete exige
            // una columna real, y el valor sale de $row.
            Column::make('Órdenes', 'id')
                ->label(fn($row) => (int) $row->ordenes)
                ->setCustomSlug('ordenes'),

            Column::make('Acciones', 'id')
                ->format(function ($value, $row, Column $column) {
                    return view('livewire.cliente.actions-buttons', ['row' => $row]);
                }),
        ];
    }

    #[On('refreshClienteTable')]
    public function refreshClienteTable()
    {
        $this->builder();
    }

    public function openClienteEditModal($id)
    {
        $this->dispatch('openClienteEditModal', $id);
    }

    public function openClienteDestroyModal($id)
    {
        $this->dispatch('openClienteDestroyModal', $id);
    }

    public function openClienteHistorial($id)
    {
        return redirect()->route('clientes.historial', $id);
    }
}
