<?php

namespace App\Livewire\CompraLote;

use App\Enums\ProductoEstado;
use App\Enums\ProductoVersion;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use App\Models\Producto;
use App\Models\ProductoModelo;
use App\Models\Sucursal;
use Livewire\Attributes\On;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\Views\Filters\MultiSelectDropdownFilter;

class CompraLoteTable extends DataTableComponent
{
    protected $model = Producto::class;
    public $compraId; // Add this property

    /** Boton de camara en la busqueda (vendor/livewire-tables/.../search-field). */
    public bool $buscarConEscaner = true;

    public function mount($compraId)
    {
        $this->compraId = is_numeric($compraId) ? (int) $compraId : null;
    }

    public function configure(): void
    {
        $this->setPrimaryKey('id')
            ->setSearchPlaceholder('Buscar por modelo, IMEI, SKU o código de barras...');
        $this->setAdditionalSelects(['productos.dado_de_baja_at', 'productos.motivo_baja',
            'productos.costo_unidad', 'productos.costo_moneda', 'productos.costo_moneda_monto', 'productos.costo_tipo_cambio']);
    }

    public function filters(): array
    {
        return [
            MultiSelectDropdownFilter::make('Estado')
                ->options(ProductoEstado::toSelectArray()->toArray())
                ->filter(function (Builder $builder, array $values) {
                    if (empty($values)) {
                        return;
                    }
                    $builder->whereIn('estado', $values);
                })
                ->setFilterDefaultValue(array_values(array_diff(ProductoEstado::values(), ProductoEstado::vendidos()))),
            MultiSelectDropdownFilter::make('Modelos')
                ->options(
                    ProductoModelo::orderBy('nombre', 'asc')
                        ->get()
                        ->keyBy('id')
                        ->map(fn($modelo) => $modelo->nombre)
                        ->toArray()
                )
                ->filter(function (Builder $builder, array $values) {
                    $builder->whereIn('producto_modelo_id', $values);
                }),
            MultiSelectDropdownFilter::make('Sucursal')
                ->options(
                    Sucursal::orderBy('nombre', 'asc')
                        ->get()
                        ->keyBy('id')
                        ->map(fn($sucursal) => $sucursal->nombre)
                        ->toArray()
                )
                ->filter(function (Builder $builder, array $values) {
                    $builder->whereIn('sucursal_id', $values);
                }),
            MultiSelectDropdownFilter::make('Versión')
                ->options(
                    collect(ProductoVersion::cases())
                        ->mapWithKeys(fn($case) => [$case->value => $case->value])
                        ->toArray()
                )
                ->filter(function (Builder $builder, array $values) {
                    $builder->whereIn('version', $values);
                }),
        ];
    }

    public function columns(): array
    {
        return [

            Column::make("ID", "id")
                ->sortable(),
            Column::make("Foto")
                ->label(fn($row) => \App\Http\Controllers\ProductoMiniaturaController::html($row->primera_imagen_id, $row->id))
                ->html()
                ->collapseOnTablet(),
            Column::make("Modelo", "modelo.nombre")
                ->sortable()
                ->searchable(),
            Column::make("Capacidad", "almacenamiento")
                ->sortable(),
            Column::make("Version", "version")
                ->sortable()
                ->collapseOnTablet(),
            // IMEI parcial; SKU y UPC exactos, que es lo que lee la pistola.
            Column::make("Imei", "imei")
                ->sortable()
                ->searchable(fn(Builder $q, $term) => $q
                    ->orWhere('productos.imei', 'like', '%' . $term . '%')
                    ->orWhere('productos.sku', trim($term))
                    ->orWhere('productos.upc', trim($term))),
            Column::make("Batería(%)", "bateria_porcentaje")
                ->sortable()
                ->format(function ($value) {
                    return $value . '%';
                })
                ->collapseOnTablet(),
            Column::make("Color", "color")
                ->sortable()
                ->collapseOnTablet(),
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
                        'dadoDeBaja' => $row->dado_de_baja_at !== null,
                    ]);
                }),
            Column::make("Grado", "estado_grado")
                ->sortable()
                ->format(fn($value) => \App\Enums\ProductoGrado::labelDe($value))
                ->collapseOnTablet(),
            // En Bs; si se compro en dolares, debajo los USD y su tipo de cambio.
            Column::make("Costo", "costo_total")
                ->sortable()
                ->format(fn($value, $row) => 'Bs ' . number_format((float) $value, 2)
                    . ($row->costo_moneda === \App\Enums\Moneda::USD->value
                        ? '<span class="block text-xs text-gray-500">' . e($row->costoEnMoneda()) . '</span>' : ''))
                ->html()
                ->collapseOnTablet(),
            Column::make("Precio V.", "precio_vendedor")
                ->sortable()
                ->format(fn($value) => 'Bs ' . number_format((float) $value, 2))
                ->collapseOnTablet(),
            Column::make("Precio C.", "precio_cliente")
                ->sortable()
                ->format(fn($value) => 'Bs ' . number_format((float) $value, 2))
                ->collapseOnTablet(),
            Column::make("Registrado", "created_at")
                ->sortable()
                ->collapseOnTablet(),
            Column::make("Sucursal", "sucursal.nombre")
                ->sortable()
                ->collapseOnTablet(),
            Column::make("Fecha Venta.", "id")
                ->sortable()
                ->format(function ($value, $row) {
                    return $row->ventaDetalle ? $row->ventaDetalle->created_at->format('d/m/Y') : '-';
                })
                ->collapseOnTablet(),
            Column::make('Acciones', 'id')
                ->format(function ($value, $row, Column $column) {
                    return view('livewire.compra-lote.actions-buttons', [
                        'row' => $row
                    ]);
                }),
        ];
    }

    public function openReclamoAbrirModal($id): void
    {
        abort_unless(auth()->user()->can('compra.reclamo'), 403);

        $this->dispatch('openReclamoAbrirModal', (int) $id);
    }

    #[On('refreshProductoTable')]
    #[On('refreshCompraDetalle')]
    #[On('reclamosActualizados')]
    public function refreshCompraLoteTable()
    {
        $this->builder();
    }

    public function openCompraLoteProductoEditModal($id)
    {
        $producto = Producto::find($id);
        if (in_array($producto->estado, ProductoEstado::vendidos(), true) || $producto->estaDadoDeBaja()) {
            toastr()->error('El equipo ya está vendido o dado de baja: no se edita desde la compra.');
            return;
        }
        $this->dispatch('openCompraLoteProductoEditModal', $id);
    }

    public function openCompraLoteProductoDestroyModal($id)
    {
        $this->dispatch('openCompraLoteProductoDestroyModal', $id);
    }

    public function cambiarEstado($id)
    {
        $this->dispatch('openProductoEstadoModal', $id);
    }

    public function builder(): Builder
    {

        if (!$this->compraId) {
            return Producto::query()->where('id', -1);
        }

        // Los equipos de la compra, por su linea (productos ya no tiene compra_id).
        return Producto::query()
            ->addSelect(['primera_imagen_id' => \App\Http\Controllers\ProductoMiniaturaController::subconsultaPrimera()])
            ->with('ventaDetalle')
            ->whereHas('compraDetalle', fn($q) => $q->where('compra_id', $this->compraId))
            ->orderBy('productos.created_at', 'desc');
    }
}
