<?php

namespace App\Livewire\Venta;

use App\Enums\LineaTipo;
use App\Enums\ProductoEstado;
use App\Enums\ReparacionTipo;
use App\Models\Producto;
use App\Models\VentaDetalle as Linea;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use App\Support\Dinero;

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
            ->setAdditionalSelects(['ventas_detalles.producto_id', 'ventas_detalles.repuesto_id', 'ventas_detalles.accesorio_id', 'ventas_detalles.producto_reparacion_repuesto_id', 'ventas_detalles.producto_asociado_id', 'ventas_detalles.producto_regalo_id', 'ventas_detalles.garantia_meses']);
    }

    public function columns(): array
    {
        return [
            // En el celular solo Detalle y Acciones (el menu de
            // garantia y trabajo externo): el resto va al desplegable de la
            // fila. Antes la tabla no cabia y Acciones quedaba fuera de la pantalla.
            Column::make('Tipo', 'tipo')
                ->format(fn($value) => LineaTipo::badge($value))
                ->html()
                ->collapseOnMobile(),
            Column::make('Detalle', 'id')
                ->format(fn($value, $row) => '<div class="whitespace-normal min-w-[9rem]">' . ($row->producto_asociado_id ? '<span class="text-gray-400">↳</span> ' : '') . e($row->descripcion())
                    . ($row->producto_id && $row->producto?->upc ? '<span class="block text-xs text-gray-500 font-mono break-all">Serial ' . e($row->producto->upc) . '</span>' : '')
                    . ($row->producto_asociado_id ? '<span class="block text-xs text-gray-500">Con ' . e(trim(($row->productoAsociado?->modelo?->nombre ?? 'el equipo') . ' ' . $row->productoAsociado?->almacenamiento)) . '</span>' : '')
                    . ($row->esRegalo() ? '<span class="block text-xs text-pink-700">Regalo con el equipo (sin cargo; el stock salió al regalarlo)</span>' : '')
                    . ($row->esCobro() ? '<span class="block text-xs text-green-700">Cobro de taller (stock ya descontado en la reparación)</span>' : '') . '</div>')
                ->html(),
            Column::make('Cant.', 'cantidad')
                ->collapseOnMobile(),
            Column::make('Precio', 'precio')
                ->format(fn($v) => Dinero::usd((float) $v))
                ->collapseOnTablet(),
            Column::make('Desc.', 'descuento')
                ->format(fn($v) => Dinero::usd((float) $v))
                ->collapseOnTablet(),
            Column::make('Subtotal', 'subtotal')
                ->format(fn($v) => Dinero::usd((float) $v))
                ->collapseOnMobile(),
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

    /*
     * Garantia y trabajo externo desde el detalle (pedido de la tienda): cuando
     * el cliente vuelve, se busca su venta por el nombre y desde ahi se ve cual
     * es el equipo. Abren el mismo modal que la tabla de Productos
     * (ProductoReparacionClienteModal), que vuelve a comprobar permiso y vendido.
     */
    public function openGarantia($productoId): void
    {
        $this->abrirReparacionCliente((int) $productoId, ReparacionTipo::Garantia, 'producto.garantia', 'openProductoGarantiaModal');
    }

    public function openTrabajoExterno($productoId): void
    {
        $this->abrirReparacionCliente((int) $productoId, ReparacionTipo::Externo, 'producto.trabajo-externo', 'openProductoTrabajoExternoModal');
    }

    private function abrirReparacionCliente(int $productoId, ReparacionTipo $tipo, string $permiso, string $evento): void
    {
        abort_unless(Auth::user()?->can($permiso), 403);

        // Solo un equipo de ESTA venta: el id llega del navegador.
        abort_unless(Linea::where('venta_id', $this->ventaId)->where('producto_id', $productoId)->exists(), 404);

        $producto = Producto::findOrFail($productoId);
        if (!in_array($producto->estado, ProductoEstado::vendidos(), true)) {
            toastr()->error('El equipo debe estar vendido para abrirle ' . mb_strtolower($tipo->label()) . '.');

            return;
        }

        $this->dispatch($evento, $productoId);
    }
}
