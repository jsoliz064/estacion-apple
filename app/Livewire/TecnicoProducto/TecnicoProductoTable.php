<?php

namespace App\Livewire\TecnicoProducto;

use App\Enums\ComisionEstado;
use App\Enums\ReparacionTipo;
use App\Models\ProductoReparacion;
use App\Models\Tecnicos;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Livewire\Attributes\On;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\DateFilter;

class TecnicoProductoTable extends DataTableComponent
{
    protected $model = ProductoReparacion::class;

    public $tecnico;

    public function mount($tecnico_id)
    {
        $this->tecnico = Tecnicos::find($tecnico_id);
    }

    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

    public function columns(): array
    {
        $columns = [
            Column::make("ID", "id")
                ->sortable(),
            Column::make("Producto", "producto_id")
                ->sortable()
                ->searchable()
                ->format(function ($value, $row) {
                    $producto = $row->producto;
                    $modelo = $producto->modelo;
                    $imei = substr($producto->imei, -4);
                    return "{$modelo->nombre} {$producto->color}, {$producto->bateria_porcentaje}% - {$imei}";
                }),
            Column::make("Descripcion", "repuestos_tecnico")
                ->sortable(),
            Column::make("Estado Reparación", "estado")
                ->sortable()
                ->format(function ($value) {
                    $color = $value == 'Pendiente' ? 'orange' : 'green';
                    return '<span style="color:' . $color . '">' . $value . '</span>';
                })
                ->html(),
            // La comision del tecnico (ComisionService) reemplaza al viejo
            // "pagado", que pagaba el 100 % de la mano de obra sin registro.
            Column::make("Comisión")
                ->label(function ($row) {
                    $comision = $row->comision;
                    if (!$comision) {
                        return '<span class="text-gray-400">—</span>';
                    }
                    return 'Bs. ' . number_format((float) $comision->monto, 2) . ' ' . $comision->estado()->badge();
                })
                ->html(),
            Column::make("Garantia Tecnico", "garantia_tecnico")
                ->sortable()
                ->format(function ($value) {
                    $color = (bool)$value ?  'green' : 'gray';
                    $text = (bool)$value ? 'Si' : '-';
                    return '<span style="color:' . $color . '">' . $text . '</span>';
                })
                ->html(),
            // El trabajo externo aparece aqui automaticamente (la consulta
            // filtra solo por tecnico), asi que sin esta columna no se
            // distingue de una reparacion normal.
            Column::make("Tipo", "tipo")
                ->sortable()
                ->format(function ($value) {
                    $tipo = ReparacionTipo::tryFrom((string) $value) ?? ReparacionTipo::Normal;
                    return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium '
                        . $tipo->badgeClasses() . '">' . $tipo->label() . '</span>';
                })
                ->html(),
            Column::make("Garantia Venta", "venta_id")
                ->sortable()
                ->format(function ($value) {
                    $color = $value ?  'green' : 'gray';
                    $text = $value ? 'Si' : '-';
                    return '<span style="color:' . $color . '">' . $text . '</span>';
                })
                ->html(),
            Column::make("Costo Reparacion", "costo")
                ->sortable()
                ->format(function ($value) {
                    return 'Bs. ' . number_format($value, 2);
                }),

        ];

        array_push(
            $columns,
            Column::make("Costo Total", "costo_total")
                ->sortable()
                ->format(function ($value) {
                    return 'Bs. ' . number_format($value, 2);
                }),
        );

        array_push(
            $columns,
            Column::make("Fecha de Entrega", "fecha_entrega")
                ->sortable(),
            Column::make("Fecha de Recogida", "fecha_recogida")
                ->sortable(),
        );

        array_push(
            $columns,
            Column::make("Acciones", "id")
                ->sortable()
                ->format(function ($value, $row) {
                    return view('livewire.tecnico-producto.actions-buttons', [
                        'id' => $row->id
                    ]);
                })
        );

        return $columns;
    }

    public function filters(): array
    {
        return [
            DateFilter::make('Fecha Entrega')
                ->filter(function (Builder $builder, string $value) {
                    $builder->where('productos_reparaciones.fecha_entrega', $value);
                }),
            SelectFilter::make('Reparaciones')
                ->options([
                    'Pendiente' => 'Pendientes',
                    'Terminado' => 'Terminados',
                ])
                ->setFilterDefaultValue('Pendiente')
                ->filter(function (Builder $builder, string $value) {
                    $builder->where('productos_reparaciones.estado', $value);
                }),
            SelectFilter::make('Comisión')
                ->options(['' => 'Todas'] + array_combine(ComisionEstado::values(), ComisionEstado::values()))
                ->filter(function (Builder $builder, string $value) {
                    $builder->whereHas('comision', fn($q) => $q->conEstado($value));
                })
        ];
    }

    public function builder(): Builder
    {
        return ProductoReparacion::query()->with(['producto.modelo', 'comision'])->where('tecnico_id', $this->tecnico->id);
    }

    #[On('refreshTecnicoProductoTable')]
    public function refreshTecnicoProductoTable()
    {
        $this->builder();
    }

    public function openReparacionEdit($id)
    {
        $this->dispatch('openReparacionEditModal', $id);
    }

    public function openReparacionShow($id)
    {
        $this->dispatch('openReparacionShowModal', $id);
    }
}
