<?php

namespace App\Livewire\User;

use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class UserTable extends DataTableComponent
{
    protected $model = User::class;


    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

    /**
     * Los roles van precargados: antes la columna Rol hacia User::find($id) y
     * luego getRoleNames(), dos consultas por fila. Con la relacion cargada,
     * rol_name() la lee de memoria.
     */
    public function builder(): Builder
    {
        return User::query()->with('roles');
    }

    public function columns(): array
    {
        return [
            Column::make("Id", "id")
                ->sortable(),
            Column::make("Nombre", "name")
                ->sortable()
                ->searchable(),
            Column::make("Correo", "email")
                ->sortable()
                ->searchable(),
            Column::make("Rol", 'id')
                ->format(
                    fn($value, $row) => $row->rol_name()
                )
                ->sortable(),
            Column::make("% Comisión", "comision_porcentaje")
                ->sortable()
                ->format(fn($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.') . ' %'),
            Column::make("Estado", "activo")
                ->sortable()
                ->format(fn($value) => $value
                    ? '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Activo</span>'
                    : '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300">Inactivo</span>')
                ->html(),
            Column::make("Creado", "created_at")
                ->sortable(),
            Column::make('Acciones', 'id')
                ->format(function ($value, $row, Column $column) {
                    return view('livewire.user.actions-buttons', [
                        'row' => $row
                    ]);
                }),
        ];
    }

    #[On('refreshUserTable')]
    public function refreshUserTable()
    {
        $this->builder();
    }

    public function openUserHistorial($id)
    {
        return redirect()->route('users.historial', $id);
    }

    public function openUserEditModal($id)
    {
        $this->dispatch('openUserEditModal', $id);
    }

    public function openUserDestroyModal($id)
    {
        $this->dispatch('openUserDestroyModal', $id);
    }
}
