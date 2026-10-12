<?php

namespace App\Livewire\RepuestoCategoria;

use App\Models\RepuestoCategoria;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Livewire\Attributes\On;

class RepuestoCategoriaTable extends DataTableComponent
{
    protected $model = RepuestoCategoria::class;

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
                    return view('livewire.repuesto-categoria.actions-buttons', [
                        'row' => $row
                    ]);
                }),
        ];
    }

    #[On('refreshRepuestoCategoriaTable')]
    public function refreshRepuestoCategoriaTable()
    {
        $this->builder();
    }

    public function openRepuestoCategoriaEditModal($id)
    {
        $this->dispatch('openRepuestoCategoriaEditModal', $id);
    }

    public function openRepuestoCategoriaDestroyModal($id)
    {
        $this->dispatch('openRepuestoCategoriaDestroyModal', $id);
    }
}
