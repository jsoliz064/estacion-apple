<?php

namespace App\Livewire\Role;

use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Livewire\Attributes\On;
use Spatie\Permission\Models\Role;

class RoleTable extends DataTableComponent
{
    protected $model = Role::class;


    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

    public function columns(): array
    {
        return [
            Column::make("Id", "id")
                ->sortable(),
            Column::make("Name", "name")
                ->sortable()
                ->searchable(),
            Column::make('Acciones', 'id')
                ->format(function ($value, $row, Column $column) {
                    return view('livewire.role.actions-buttons', [
                        'row' => $row
                    ]);
                }),
        ];
    }

    #[On('refreshRoleTable')]
    public function refreshRoleTable()
    {
        $this->builder();
    }

    public function openRoleEditModal($id)
    {
        $this->dispatch('openRoleEditModal', $id);
    }

    public function openRoleDestroyModal($id)
    {
        $this->dispatch('openRoleDestroyModal', $id);
    }
}
