<?php

namespace App\Livewire\Venta;

use App\Enums\LineaTipo;
use App\Models\VentaDetalle as Linea;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

/** Lo ultimo que se vendio, linea por linea (lo usa el tablero de inicio). */
class VentaProductoTable extends DataTableComponent
{
    protected $model = Linea::class;

    public function configure(): void
    {
        $this->setTableName('ultimas_lineas');
        $this->setPrimaryKey('id')
            ->setDefaultSort('ventas_detalles.id', 'desc')
            ->setSearchDisabled()
            ->setAdditionalSelects(['ventas_detalles.producto_id', 'ventas_detalles.repuesto_id', 'ventas_detalles.accesorio_id']);
    }

    public function columns(): array
    {
        return [
            Column::make('Venta', 'venta_id')
                ->format(fn($v) => '#' . $v),
            Column::make('Fecha', 'created_at')
                ->format(fn($v) => Carbon::parse($v)->format('d/m/Y H:i')),
            Column::make('Tipo', 'tipo')
                ->format(fn($v) => LineaTipo::badge($v))
                ->html(),
            Column::make('Detalle', 'id')
                ->format(fn($v, $row) => $row->descripcion()),
            Column::make('Cant.', 'cantidad'),
            Column::make('Subtotal', 'subtotal')
                ->format(fn($v) => 'Bs ' . number_format((float) $v, 2)),
            Column::make('Sucursal', 'sucursal.nombre')
                ->collapseOnTablet(),
        ];
    }

    public function builder(): Builder
    {
        return Linea::query()->with(['producto.modelo', 'repuesto', 'accesorio']);
    }

    #[On('refreshVentaTable')]
    public function refrescar()
    {
        $this->builder();
    }
}
