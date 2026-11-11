<?php

namespace App\Livewire\Producto;

use App\Enums\BajaMotivo;
use App\Enums\ProductoEstado;
use App\Enums\ProductoGrado;
use App\Enums\ProductoTipoVenta;
use App\Enums\ProductoVersion;
use App\Enums\ReparacionTipo;
use App\Models\Producto;
use App\Models\Sucursal;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Livewire\Attributes\On;
use Rappasoft\LaravelLivewireTables\Views\Filters\MultiSelectDropdownFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class ProductoTable extends DataTableComponent
{
    protected $model = Producto::class;
    public $selectedModelId = null;

    /** Boton de camara en la busqueda (vendor/livewire-tables/.../search-field). */
    public bool $buscarConEscaner = true;

    protected $listeners = ['modelSelected', 'modelCleared'];

    public function modelSelected($modelId)
    {
        $this->selectedModelId = $modelId;
        $this->resetPage(); // Reset pagination when model changes
    }

    public function modelCleared()
    {
        $this->selectedModelId = null;
        $this->resetPage(); // Reset pagination when clearing filter
    }

    public function configure(): void
    {
        $this->setPrimaryKey('id')
            ->setSearchPlaceholder('Buscar por modelo, IMEI o código de barras...');

        // Las columnas con ->label() se excluyen del SELECT que arma el paquete,
        // así que los campos que usan sus callbacks hay que pedirlos aquí.
        $this->setAdditionalSelects([
            'productos.costo_unidad',
            'productos.costo_regalos',
            'productos.moneda',
            'productos.costo_moneda_monto',
            'productos.precio_moneda_monto',
            'productos.tipo_cambio',
            'productos.tipo_venta',
            'productos.dado_de_baja_at',
            'productos.motivo_baja',
        ]);
    }

    public function columns(): array
    {
        // En USD; las celdas densas no llevan el equivalente en Bs.
        $usd = fn($value) => \App\Support\Dinero::usd($value);

        return [
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
            // IMEI parcial; el UPC, exacto: es lo que lee la pistola en la caja
            // (y trae a todos los del mismo modelo) o en un sticker propio.
            Column::make("Imei", "imei")
                ->sortable()
                ->searchable(fn(Builder $q, $term) => $q
                    ->orWhere('productos.imei', 'like', '%' . $term . '%')
                    ->orWhere('productos.upc', trim($term))),
            Column::make("Batería %", "bateria_porcentaje")
                ->sortable()
                ->format(function ($value) {
                    return $value . '%';
                })
                ->collapseOnTablet(),
            Column::make("Color", "color")
                ->sortable()
                ->collapseOnTablet(),
            Column::make("Grado", "estado_grado")
                ->sortable()
                ->format(fn($value) => ProductoGrado::labelDe($value))
                ->collapseOnTablet(),
            Column::make("Tipo de venta")
                ->label(function ($row) {
                    $tipo = ProductoTipoVenta::tryFrom((string) $row->tipo_venta);
                    if (!$tipo || $tipo === ProductoTipoVenta::Venta) {
                        return '<span class="text-gray-400">-</span>';
                    }
                    return '<span class="px-2 py-0.5 rounded text-xs ' . $tipo->badgeClasses() . '">' . e($tipo->label()) . '</span>';
                })
                ->html()
                ->collapseOnTablet(),
            Column::make("Detalles", "detalles")
                ->sortable()
                ->format(fn($value) => str($value)->limit(20))
                ->collapseOnTablet(),
            Column::make("Sucursal", "sucursal.nombre")
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
                        'dadoDeBaja' => $row->dado_de_baja_at !== null,
                    ]);
                }),
            // Antes leia productos.compra_id con un Compra::find por fila; la
            // compra ahora sale de su linea (compras_detalles), cargada de una
            // vez en el builder.
            Column::make("Compra")
                ->label(function ($row) {
                    $compra = $row->compraDetalle?->compra;
                    if (!$compra) {
                        // Sin compra: lo entrego un cliente en permuta.
                        return $row->permuta ? "Permuta, venta #{$row->permuta->venta_id}" : '';
                    }
                    return "Compra #{$compra->id}, {$compra->proveedor?->nombre}, " . $compra->fecha?->format('d/m/Y');
                })
                ->collapseOnTablet(),
            // El precio si lleva su equivalente en Bs (lo que se le dice al
            // cliente), al TC del dia, como <x-monto>: los Bs son una vista.
            Column::make("Precio", "precio")
                ->sortable()
                ->format(fn($value) => '<span class="inline-flex flex-col leading-tight">'
                    . '<span class="whitespace-nowrap">' . e(\App\Support\Dinero::usd($value)) . '</span>'
                    . '<span class="whitespace-nowrap text-xs font-normal opacity-70">' . e(\App\Support\Dinero::bs($value)) . '</span>'
                    . '</span>')
                ->html()
                ->collapseOnTablet(),
            Column::make("Venta")
                ->label(function ($row) use ($usd) {
                    $linea = $row->ventaDetalle;
                    if (!$linea) {
                        return '';
                    }
                    return "Venta #{$linea->venta_id}, {$usd($linea->subtotal)}, " . $linea->created_at?->format('d/m/Y');
                })
                ->collapseOnTablet(),
            Column::make("Baja")
                ->label(fn($row) => $row->dado_de_baja_at
                    ? '<span style="color:gray">' . e(BajaMotivo::labelDe($row->motivo_baja)) . ', ' . $row->dado_de_baja_at->format('d/m/Y') . '</span>'
                    : '<span style="color:gray">-</span>')
                ->html()
                ->collapseOnTablet(),
            Column::make("Garantia")
                ->label(fn($row) => $this->distintivo((bool) $row->garantia_pendiente, 'Activa'))
                ->html()
                ->collapseOnTablet(),
            Column::make("Trabajo Externo")
                ->label(fn($row) => $this->distintivo((bool) $row->trabajo_externo_pendiente, 'En curso'))
                ->html()
                ->collapseOnTablet(),
            Column::make('Acciones', 'id')
                ->format(function ($value, $row) {
                    return view('livewire.producto.actions-buttons', [
                        'row' => $row
                    ]);
                }),
        ];
    }

    /** Distintivo de las columnas deducidas. Sin safelist: color en linea. */
    private function distintivo(bool $activo, string $texto): string
    {
        return $activo
            ? '<span style="color:green">' . $texto . '</span>'
            : '<span style="color:gray">-</span>';
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
                    // Un telefono en trabajo externo esta SIEMPRE vendido, que
                    // el valor por defecto de este filtro oculta. Como los
                    // filtros se combinan con AND, sin esto el filtro de trabajo
                    // externo devolveria siempre cero resultados.
                    if ($this->getAppliedFilterWithValue('trabajo_externo')) {
                        return;
                    }
                    $builder->whereIn('productos.estado', $values);
                })
                // Por defecto, todo lo que sigue en el negocio: lo vendido se
                // ve eligiendolo.
                ->setFilterDefaultValue(array_values(array_diff(ProductoEstado::values(), ProductoEstado::vendidos()))),
            // La baja archiva el equipo: por defecto NO se ve. La clave del
            // filtro (Str::snake) es 'baja'.
            SelectFilter::make('Baja')
                ->options([
                    'vigentes' => 'Sin los dados de baja',
                    'baja' => 'Solo los dados de baja',
                    'todos' => 'Todos',
                ])
                ->filter(function (Builder $builder, string $value) {
                    match ($value) {
                        'baja' => $builder->whereNotNull('productos.dado_de_baja_at'),
                        'todos' => null,
                        default => $builder->whereNull('productos.dado_de_baja_at'),
                    };
                })
                ->setFilterDefaultValue('vigentes'),
            MultiSelectDropdownFilter::make('Sucursal')
                ->options(
                    Sucursal::orderBy('nombre', 'asc')
                        ->get()
                        ->keyBy('id')
                        ->map(fn($sucursal) => $sucursal->nombre)
                        ->toArray()
                )
                ->filter(function (Builder $builder, array $values) {
                    $builder->whereIn('productos.sucursal_id', $values);
                }),
            MultiSelectDropdownFilter::make('Grado')
                ->options(ProductoGrado::toSelectArray()->toArray())
                ->filter(function (Builder $builder, array $values) {
                    $builder->whereIn('productos.estado_grado', $values);
                }),
            MultiSelectDropdownFilter::make('Tipo de venta', 'tipo_venta')
                ->options(ProductoTipoVenta::toSelectArray()->toArray())
                ->filter(function (Builder $builder, array $values) {
                    $builder->whereIn('productos.tipo_venta', $values);
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
            // La clave del filtro es Str::snake del nombre: 'trabajo_externo'.
            // Es la que consulta el filtro de Estado para apartarse.
            MultiSelectDropdownFilter::make('Trabajo Externo', 'trabajo_externo')
                ->options([
                    'si' => 'Con trabajo externo',
                    'no' => 'Sin trabajo externo',
                ])
                ->filter(function (Builder $builder, array $values) {
                    if (empty($values) || count($values) === 2) {
                        return;
                    }
                    $conTrabajo = in_array('si', $values, true);
                    $builder->whereHas(
                        'reparaciones',
                        fn($query) => $query
                            ->where('tipo', ReparacionTipo::Externo->value)
                            ->where('estado', 'Pendiente'),
                        $conTrabajo ? '>=' : '<',
                        1
                    );
                }),
        ];
    }

    public function builder(): Builder
    {
        return Producto::query()
            ->addSelect(['primera_imagen_id' => \App\Http\Controllers\ProductoMiniaturaController::subconsultaPrimera()])
            // La compra y la venta salen de sus lineas: cargadas de una vez,
            // sin un find por fila.
            ->with(['compraDetalle.compra.proveedor', 'ventaDetalle', 'permuta'])
            // Los distintivos se DEDUCEN de si hay una reparacion pendiente de
            // ese tipo, en vez de leer una bandera guardada. La bandera
            // `garantia_activa` solo se apagaba desde su propio modal, asi que
            // cerrar la reparacion desde la pantalla del tecnico la dejaba
            // encendida para siempre. Una subconsulta por columna, sin N+1.
            ->withExists([
                'reparaciones as garantia_pendiente' => fn($query) => $query
                    ->where('tipo', ReparacionTipo::Garantia->value)
                    ->where('estado', 'Pendiente'),
                'reparaciones as trabajo_externo_pendiente' => fn($query) => $query
                    ->where('tipo', ReparacionTipo::Externo->value)
                    ->where('estado', 'Pendiente'),
            ])
            ->when($this->selectedModelId, function ($query) {
                $query->where('producto_modelo_id', $this->selectedModelId);
            })->orderby('productos.created_at', 'desc');
    }


    #[On('refreshProductoTable')]
    public function refreshProductoTable()
    {
        $this->builder();
    }

    public function changeVentaRapida($id)
    {
        $producto = Producto::find($id);
        $producto->venta_rapida = !$producto->venta_rapida;
        $producto->save();
    }

    public function cambiarEstado($id)
    {
        $producto = Producto::find($id);
        if ($producto->estaDadoDeBaja()) {
            toastr()->error('El equipo está dado de baja: revierte la baja para cambiarle el estado.');
            return;
        }
        if ($producto->estado == ProductoEstado::Reparacion->value) {
            $reparacion = $producto->ultimaReparacionPendiente();
            $this->dispatch('openReparacionEditModal', $reparacion->id);
        } else {
            $this->dispatch('openProductoEstadoModal', $id);
        }
    }

    public function openProductoEditModal($id)
    {
        $producto = Producto::find($id);
        if (!$producto->estaDisponible()) {
            toastr()->error('El producto debe estar en inventario');
            return;
        }
        $this->dispatch('openCompraLoteProductoEditModal', $id);
    }

    public function openProductoGarantiaModal($id)
    {
        $producto = Producto::find($id);
        if (!in_array($producto->estado, ProductoEstado::vendidos(), true)) {
            toastr()->error('El producto debe estar vendido');
            return;
        }
        $this->dispatch('openProductoGarantiaModal', $id);
    }

    public function openProductoTrabajoExternoModal($id)
    {
        $producto = Producto::find($id);

        // Misma comprobacion que en el menu, repetida en el servidor: el @if
        // del blade solo esconde el boton.
        if (!in_array($producto->estado, ProductoEstado::vendidos(), true)) {
            toastr()->error('El producto debe estar vendido');
            return;
        }

        $this->dispatch('openProductoTrabajoExternoModal', $id);
    }

    public function openProductoBajaModal($id)
    {
        abort_unless(auth()->user()->can('producto.baja'), 403);
        $this->dispatch('openProductoBajaModal', $id);
    }

    public function openProductoRegalosModal($id)
    {
        abort_unless(auth()->user()->can('producto.regalos'), 403);
        $this->dispatch('openProductoRegalosModal', $id);
    }

    public function openProductoDestroyModal($id)
    {
        $producto = Producto::find($id);
        if (!$producto->estaDisponible()) {
            toastr()->error('El producto debe estar en el inventario');
            return;
        }
        $this->dispatch('openProductoDestroyModal', $id);
    }

    public function openProductoHistorial($id)
    {
        return redirect()->route('productos.historial', $id);
    }
}
