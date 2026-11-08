<?php

namespace App\Livewire\Color;

use App\Models\Color;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class ColorTable extends DataTableComponent
{
    protected $model = Color::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id')
            ->setDefaultSort('orden', 'asc')
            ->setAdditionalSelects(['colores.hex'])
            ->setSearchPlaceholder('Buscar por nombre...')
            ->setEmptyMessage('Todavía no hay colores.');
    }

    public function builder(): Builder
    {
        // Los equipos se cuentan por nombre: productos.color es texto.
        return Color::query()
            ->withCount('modelos')
            ->addSelect(['equipos_count' => DB::table('productos')
                ->selectRaw('COUNT(*)')
                ->whereColumn('productos.color', 'colores.nombre')]);
    }

    public function columns(): array
    {
        return [
            Column::make('Orden', 'orden')->sortable(),

            Column::make('Color', 'nombre')
                ->sortable()
                ->searchable()
                ->format(fn($value, $row) => '<span class="inline-flex items-center rounded-full border border-gray-300 dark:border-gray-600 px-3 py-1 text-xs font-medium" style="'
                    . $row->estilo() . '">' . e($value) . '</span>')
                ->html(),

            Column::make('Muestra', 'hex')
                ->format(fn($value) => '<span class="font-mono text-xs">' . e($value) . '</span>')
                ->html()
                ->collapseOnTablet(),

            Column::make('Modelos')
                ->label(fn($row) => $row->modelos_count ? $row->modelos_count . ' modelo(s)' : '<span class="text-gray-400">Todos</span>')
                ->html(),

            Column::make('Equipos')
                ->label(fn($row) => (string) (int) $row->equipos_count),

            Column::make('Estado', 'activo')
                ->sortable()
                ->format(fn($value) => $value
                    ? '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Activo</span>'
                    : '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300">Inactivo</span>')
                ->html(),

            Column::make('Acciones', 'id')
                ->format(fn($value, $row, Column $column) => view('livewire.color.actions-buttons', ['row' => $row])),
        ];
    }

    #[On('refreshColorTable')]
    public function refreshColorTable()
    {
        $this->builder();
    }

    public function openColorEditModal($id)
    {
        $this->dispatch('openColorFormModal', $id);
    }

    public function openColorDestroyModal($id)
    {
        $this->dispatch('openColorDestroyModal', $id);
    }
}
