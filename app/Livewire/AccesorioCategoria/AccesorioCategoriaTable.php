<?php

namespace App\Livewire\AccesorioCategoria;

use App\Models\AccesorioCategoria;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Livewire\Attributes\On;

class AccesorioCategoriaTable extends DataTableComponent
{
    protected $model = AccesorioCategoria::class;

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
            Column::make("Descripcion", "descripcion")
                ->sortable()
                ->searchable(),
            Column::make("Created at", "created_at")
                ->sortable(),
            Column::make('Acciones', 'id')
                ->format(function ($value, $row, Column $column) {
                    return view('livewire.accesorio-categoria.actions-buttons', [
                        'row' => $row
                    ]);
                }),
        ];
    }

    #[On('refreshAccesorioCategoriaTable')]
    public function refreshAccesorioCategoriaTable()
    {
        $this->builder();
    }

    public function openAccesorioCategoriaEditModal($id)
    {
        $this->dispatch('openAccesorioCategoriaEditModal', $id);
    }

    public function openAccesorioCategoriaDestroyModal($id)
    {
        $this->dispatch('openAccesorioCategoriaDestroyModal', $id);
    }
}
