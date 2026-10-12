<?php

namespace App\Livewire\Venta;

use App\Enums\LineaTipo;
use App\Models\VentaDetalle as Linea;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

/** Las lineas de una venta: equipos, repuestos, accesorios y cobros de taller. */
class VentaDetalleTable extends DataTableComponent
{
    protected $model = Linea::class;

    #[Locked]
    public int $ventaId;

    public function mount($ventaId)
    {
        $this->ventaId = (int) $ventaId;
    }

    public function configure(): void
    {
        $this->setTableName('lineas');
        $this->setPrimaryKey('id')
            ->setSearchDisabled()
            ->setPaginationDisabled()
            ->setAdditionalSelects(['ventas_detalles.producto_id', 'ventas_detalles.repuesto_id', 'ventas_detalles.accesorio_id', 'ventas_detalles.producto_reparacion_repuesto_id', 'ventas_detalles.producto_asociado_id', 'ventas_detalles.producto_regalo_id']);
    }

    public function columns(): array
    {
        return [
            Column::make('Tipo', 'tipo')
                ->format(fn($value) => LineaTipo::badge($value))
                ->html(),
            Column::make('Detalle', 'id')
                ->format(fn($value, $row) => ($row->producto_asociado_id ? '<span class="text-gray-400">↳</span> ' : '') . e($row->descripcion())
                    . ($row->producto_asociado_id ? '<span class="block text-xs text-gray-500">Con ' . e(trim(($row->productoAsociado?->modelo?->nombre ?? 'el equipo') . ' ' . $row->productoAsociado?->almacenamiento)) . '</span>' : '')
                    . ($row->esRegalo() ? '<span class="block text-xs text-pink-700">Regalo con el equipo (sin cargo; el stock salió al regalarlo)</span>' : '')
                    . ($row->esCobro() ? '<span class="block text-xs text-green-700">Cobro de taller (stock ya descontado en la reparación)</span>' : ''))
                ->html(),
            Column::make('Cant.', 'cantidad'),
            Column::make('Precio', 'precio')
                ->format(fn($v) => 'Bs ' . number_format((float) $v, 2)),
            Column::make('Desc.', 'descuento')
                ->format(fn($v) => 'Bs ' . number_format((float) $v, 2))
                ->collapseOnTablet(),
            Column::make('Subtotal', 'subtotal')
                ->format(fn($v) => 'Bs ' . number_format((float) $v, 2)),
            Column::make('Garantía', 'garantia_fecha_exp')
                ->format(fn($v, $row) => $v ? $row->garantia_meses . ' m · vence ' . Carbon::parse($v)->format('d/m/Y') : '—')
                ->collapseOnTablet(),
            Column::make('Acciones', 'id')
                ->format(fn($value, $row) => view('livewire.venta.actions-detalle-buttons', ['row' => $row])),
        ];
    }

    public function builder(): Builder
    {
        return Linea::query()
            ->with(['producto.modelo', 'repuesto', 'accesorio', 'productoAsociado.modelo'])
            ->where('ventas_detalles.venta_id', $this->ventaId)
            // Cada equipo y, debajo, lo que se vendio con el; lo suelto al final.
            ->orderByRaw('COALESCE(ventas_detalles.producto_id, ventas_detalles.producto_asociado_id) IS NULL')
            ->orderByRaw('COALESCE(ventas_detalles.producto_id, ventas_detalles.producto_asociado_id)')
            ->orderByRaw('ventas_detalles.producto_id IS NULL')
            ->orderBy('ventas_detalles.id');
    }

    #[On('refreshVentaDetalleTable')]
    public function refreshVentaDetalleTable()
    {
        $this->builder();
    }

    public function openVentaDetalleDestroyModal($id)
    {
        $this->dispatch('openVentaDetalleDestroyModal', $id);
    }
}
