<?php

namespace App\Livewire\Tecnico;

use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use App\Models\Tecnicos;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;


class TecnicoTable extends DataTableComponent
{
    protected $model = Tecnicos::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

    public function builder(): Builder
    {
        // Las relaciones de una vez: antes cada fila cargaba todas sus
        // reparaciones para contar las pendientes.
        return Tecnicos::query()->with([
            'user:id,name',
            'reparaciones' => fn($q) => $q->select('id', 'tecnico_id', 'estado')->where('estado', 'Pendiente'),
            'comisiones' => fn($q) => $q->porPagar()->select('id', 'tecnico_id', 'monto'),
        ]);
    }

    public function columns(): array
    {
        return [
            Column::make("Id", "id")
                ->sortable(),
            Column::make("Nombre", "nombre")
                ->sortable()
                ->searchable(),
            Column::make("% Comisión", "comision_porcentaje")
                ->sortable()
                ->format(fn($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.') . ' %'),
            Column::make("Usuario")
                ->label(fn($row) => e($row->user?->name ?? '—')),
            Column::make("Productos Pendientes")
                ->label(fn($row) => $row->reparaciones->count()),
            // Su comision ganada y sin liquidar (docs/05), no la mano de obra
            // entera: eso pagaba el sistema heredado.
            Column::make("Comisión por pagar")
                ->label(fn($row) => 'Bs ' . number_format((float) $row->comisiones->sum('monto'), 2)),
            Column::make('Acciones', 'id')
                ->format(function ($value, $row, Column $column) {
                    return view('livewire.tecnico.actions-buttons', [
                        'row' => $row
                    ]);
                }),
        ];
    }

    #[On('refreshTecnicoTable')]
    public function refreshTecnicoTable()
    {
        $this->builder();
    }

    public function openTecnicoEditModal($id)
    {
        $this->dispatch('openTecnicoEditModal', $id);
    }

    public function openTecnicoDestroyModal($id)
    {
        $this->dispatch('openTecnicoDestroyModal', $id);
    }
}
