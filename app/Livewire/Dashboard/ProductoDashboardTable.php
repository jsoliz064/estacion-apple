<?php

namespace App\Livewire\Dashboard;

use App\Enums\ProductoEstado;
use App\Models\Producto;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Livewire\Attributes\On;
use Illuminate\Database\Eloquent\Builder;


class ProductoDashboardTable extends DataTableComponent
{
    protected $model = Producto::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

    public function columns(): array
    {
        return [
            Column::make("Modelo", "modelo.nombre")
                ->sortable()
                ->searchable(),
            Column::make("Capacidad", "almacenamiento")
                ->sortable(),
            Column::make("Imei", "imei")
                ->sortable()
                ->searchable(),
            Column::make("Batería(%)", "bateria_porcentaje")
                ->sortable()
                ->format(function ($value) {
                    return $value . '%';
                }),
            Column::make("Color", "color")
                ->sortable(),
            Column::make("Estado", "estado")
                ->sortable()
                ->format(function ($value, $row) {
                    $color = ProductoEstado::colorDe($value);

                    $canChangeState = ProductoEstado::puedeAbrir($value);

                    return view('livewire.producto.cambiar-estado-button', [
                        'estado' => $value,
                        'color' => $color,
                        'id' => $row->id,
                        'canChangeState' => $canChangeState,
                    ]);
                }),
        ];
    }

    public function builder(): Builder
    {
        // Los equipos que estan "fuera de la vitrina" y hay que seguir: en
        // reparacion, fuera del local o reservados. Sin los dados de baja.
        return Producto::query()->select('productos.*')->vigentes()
            ->whereIn('estado', [ProductoEstado::Reparacion->value, ProductoEstado::Fuera->value, ProductoEstado::Reserva->value]);
    }

    #[On('refreshProductoTable')]
    public function refreshProductoTable()
    {
        $this->builder();
    }

    public function cambiarEstado($id)
    {
        $this->dispatch('openProductoEstadoModal', $id);
    }
}
