<?php

namespace App\Livewire\ProductoCategoria;

use App\Models\ProductoCategoria;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Livewire\Attributes\On;

class ProductoCategoriaTable extends DataTableComponent
{
    protected $model = ProductoCategoria::class;

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
            Column::make("Marca", "productoMarca.nombre")
                ->sortable()
                ->searchable(),
            Column::make("Created at", "created_at")
                ->sortable(),
            Column::make('Acciones', 'id')
                ->format(function ($value, $row, Column $column) {
                    return view('livewire.producto-categoria.actions-buttons', [
                        'row' => $row
                    ]);
                }),
        ];
    }

    #[On('refreshProductoCategoriaTable')]
    public function refreshProductoCategoriaTable()
    {
        $this->builder();
    }

    public function openProductoCategoriaEditModal($id)
    {
        $this->dispatch('openProductoCategoriaEditModal', $id);
    }

    public function openProductoCategoriaDestroyModal($id)
    {
        $this->dispatch('openProductoCategoriaDestroyModal', $id);
    }
}
