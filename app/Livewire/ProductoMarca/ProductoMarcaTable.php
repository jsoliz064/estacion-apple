<?php

namespace App\Livewire\ProductoMarca;

use App\Models\ProductoMarca;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Livewire\Attributes\On;


class ProductoMarcaTable extends DataTableComponent
{
    protected $model = ProductoMarca::class;

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
            Column::make("Created at", "created_at")
                ->sortable(),
            Column::make('Acciones', 'id')
                ->format(function ($value, $row, Column $column) {
                    return view('livewire.producto-marca.actions-buttons', [
                        'row' => $row
                    ]);
                }),
        ];
    }

    #[On('refreshProductoMarcaTable')]
    public function refreshProductoMarcaTable()
    {
        $this->builder();
    }

    public function openProductoMarcaEditModal($id)
    {
        $this->dispatch('openProductoMarcaEditModal', $id);
    }

    public function openProductoMarcaDestroyModal($id)
    {
        $this->dispatch('openProductoMarcaDestroyModal', $id);
    }
}
