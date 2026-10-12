<?php

namespace App\Livewire\ProductoHistorial;

use App\Models\Producto;
use App\Models\ProductoReparacionRepuesto;
use App\Models\VentaDetalle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class ProductoRepuestosTable extends DataTableComponent
{
    protected $model = ProductoReparacionRepuesto::class;

    public $producto;

    /** Memo por request de la fila de totales. */
    protected ?object $totales = null;

    public function mount($producto_id)
    {
        $this->producto = Producto::findOrFail($producto_id);
    }

    public function configure(): void
    {
        // Nombre propio: el default del paquete es 'table' para TODAS las tablas,
        // y esta pantalla monta dos a la vez.
        $this->setTableName('repuestos');

        $this->setPrimaryKey('id')
            ->setDefaultSort('reparacion.created_at', 'desc')
            ->setSearchDisabled()
            ->setEmptyMessage('Este producto no registra repuestos usados en sus reparaciones.');

        // El paquete solo SELECTea los campos de las columnas declaradas, pero
        // executeQuery() hace pluck('id') para los wire:key de fila.
        $this->setAdditionalSelects([
            'productos_reparaciones_repuestos.id',
        ]);

        $this->setFooterTrAttributes(fn($rows) => [
            'default' => false,
            'class' => 'bg-gray-100 dark:bg-gray-900 font-bold text-gray-900 dark:text-white',
        ]);
    }

    /**
     * Query base que comparten builder() y getTotales().
     *
     * Se filtra por productos_reparaciones.producto_id y NO por el historial:
     * una misma reparación genera varias entradas de historial (creación,
     * edición, terminada, garantía), así que pasar por ahí duplicaría cada
     * línea de repuesto.
     *
     * Cualquier filtro que se añada más adelante debe aplicarse AQUÍ, no con un
     * where suelto en filters(), o los totales dejarán de coincidir con lo filtrado.
     */
    protected function scopedQuery(): Builder
    {
        return ProductoReparacionRepuesto::query()
            ->whereHas('reparacion', fn(Builder $query) => $query->where('producto_id', $this->producto->id));
    }

    public function builder(): Builder
    {
        // Subconsulta, no relacion: una columna en el SELECT principal y cero
        // N+1 al pintar el distintivo de "ya cobrado". El cobro es una linea
        // mas de la venta del telefono (ventas_detalles), no una venta aparte.
        return $this->scopedQuery()
            ->addSelect([
                'cobrado_en_venta' => VentaDetalle::query()
                    ->whereColumn(
                        'ventas_detalles.producto_reparacion_repuesto_id',
                        'productos_reparaciones_repuestos.id'
                    )
                    ->select('ventas_detalles.venta_id')
                    ->limit(1),
            ]);
    }

    /**
     * Totales de TODAS las filas, no solo de la página visible: el footer nativo
     * del paquete recibe $this->getRows, que es el paginador de la página actual.
     */
    protected function getTotales(): object
    {
        return $this->totales ??= $this->scopedQuery()
            ->selectRaw('COALESCE(SUM(cantidad), 0) as total_cantidad')
            ->selectRaw('COALESCE(SUM(subtotal_costo), 0) as total_costo')
            ->first();
    }

    public function columns(): array
    {
        return [
            Column::make('Repuesto', 'repuesto.nombre')
                ->sortable()
                ->footer(fn($rows) => 'TOTALES'),
            Column::make('Categoría', 'repuesto.categoria.nombre')
                ->sortable()
                ->format(fn($value) => $value ?: '—')
                ->collapseOnTablet(),
            Column::make('Modelo', 'repuesto.modelo.nombre')
                ->sortable()
                ->format(fn($value) => $value ?: '—')
                ->collapseOnTablet(),
            Column::make('Reparación', 'reparacion.id')
                ->sortable()
                ->format(fn($value) => '#' . $value)
                ->collapseOnMobile(),
            Column::make('Fecha', 'reparacion.created_at')
                ->sortable()
                ->format(fn($value) => $value ? Carbon::parse($value)->format('d/m/Y') : '—'),
            Column::make('Técnico', 'reparacion.tecnico.nombre')
                ->sortable()
                ->format(fn($value) => $value ?: 'Sin técnico')
                ->collapseOnTablet(),
            Column::make('Estado Rep.', 'reparacion.estado')
                ->sortable()
                ->format(function ($value) {
                    $classes = $value === 'Terminado'
                        ? 'bg-green-100 text-green-800'
                        : 'bg-yellow-100 text-yellow-800';

                    return '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold '
                        . $classes . '">' . e($value) . '</span>';
                })
                ->html()
                ->collapseOnMobile(),
            // El repuesto sigue costando lo mismo al equipo; esto solo dice si
            // ademas se le cobro al cliente en la venta del telefono.
            Column::make('Cobrado')
                ->label(function ($row) {
                    if (!$row->cobrado_en_venta) {
                        return '<span class="text-gray-400">—</span>';
                    }

                    return '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold '
                        . 'bg-green-100 text-green-800">En venta #' . e($row->cobrado_en_venta) . '</span>';
                })
                ->html(),
            Column::make('Cantidad', 'cantidad')
                ->sortable()
                ->footer(fn($rows) => (string) $this->getTotales()->total_cantidad),
            Column::make('Costo Unit. (Bs)', 'costo')
                ->sortable()
                ->format(fn($value) => 'Bs. ' . number_format((float) $value, 2))
                ->collapseOnTablet(),
            Column::make('Subtotal (Bs)', 'subtotal_costo')
                ->sortable()
                ->format(fn($value) => 'Bs. ' . number_format((float) $value, 2))
                ->footer(fn($rows) => 'Bs. ' . number_format((float) $this->getTotales()->total_costo, 2)),
        ];
    }
}
