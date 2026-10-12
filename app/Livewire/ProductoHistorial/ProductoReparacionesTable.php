<?php

namespace App\Livewire\ProductoHistorial;

use App\Enums\ReparacionTipo;
use App\Models\Producto;
use App\Models\ProductoReparacion;
use App\Models\ProductoReparacionRepuesto;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\MultiSelectDropdownFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;

/**
 * Las reparaciones de un telefono, una fila por reparacion.
 *
 * Es el nivel que faltaba entre las otras dos pestanas: "Todo" lista un
 * movimiento por evento (y una misma reparacion genera varios: creacion,
 * edicion, terminada, garantia), y "Repuestos" lista una fila por pieza sin la
 * mano de obra. Aqui la unidad es la reparacion, con su total al pie.
 *
 * UNIDADES: todo en Bs. costo (mano de obra del tecnico) + costo_repuestos =
 * costo_total. cobro_cliente solo tiene valor cuando tipo = Externo.
 *
 * Antes habia ademas un total en USD dividido por una tasa de cambio que se
 * envenenaba solo (un `?? 1` sin lote, un campo editable con min:0); al pasar
 * todo a Bs se fue la columna T/C con el.
 */
class ProductoReparacionesTable extends DataTableComponent
{
    protected $model = ProductoReparacion::class;

    public $producto;

    /** Memo por request de la fila de totales. */
    protected ?object $totales = null;

    public function mount($producto_id)
    {
        $this->producto = Producto::findOrFail($producto_id);
    }

    public function configure(): void
    {
        // Nombre propio: el default del paquete es 'table' para TODAS las tablas
        // y esta pantalla monta tres (query string, id del DOM y el evento
        // Alpine de filas colapsadas se pisarian entre si). Ya en uso:
        // historial, repuestos, movimientos.
        $this->setTableName('reparaciones');

        $this->setPrimaryKey('id')
            // SIN cualificar la tabla: applySorting() resuelve la clave con
            // getColumnBySelectName() y descarta con `continue` lo que no
            // matchee, asi que 'productos_reparaciones.created_at' no ordenaria
            // nada y no avisaria. La columna Fecha lleva su sortCallback.
            ->setDefaultSort('created_at', 'desc')
            // Sin busqueda: no esta implementada en scopedQuery(), y la caja se
            // mostraria igual (showSearchField() no mira las columnas) sin hacer
            // nada.
            ->setSearchDisabled()
            ->setEmptyMessage('Este producto no registra reparaciones.');

        // executeQuery() hace pluck('id') para los wire:key de fila.
        $this->setAdditionalSelects(['productos_reparaciones.id']);

        $this->setFooterTrAttributes(fn($rows) => [
            'default' => false,
            'class' => 'bg-gray-100 dark:bg-gray-900 font-bold text-gray-900 dark:text-white',
        ]);
    }

    /**
     * Query base compartida por builder() y getTotales().
     *
     * DEVUELVE UNA QUERY NUEVA EN CADA LLAMADA: NO MEMOIZAR. builder() le suma
     * un addSelect de agregado, y si getTotales() heredara ese SELECT la query
     * de totales mezclaria columnas sueltas con SUM() sin GROUP BY; MySQL la
     * rechaza porque config/database.php trae 'strict' => true, o sea
     * ONLY_FULL_GROUP_BY.
     *
     * TODO filtro se aplica AQUI y nunca con ->filter() en filters():
     * applyFilters() (Traits/WithData::baseQuery) solo toca el builder de las
     * filas, asi que el pie dejaria de cuadrar con lo que se ve.
     */
    protected function scopedQuery(): Builder
    {
        $query = ProductoReparacion::query()
            ->where('productos_reparaciones.producto_id', $this->producto->id);

        // getAppliedFilterWithValue() devuelve el valor CRUDO del query string
        // (getAppliedFiltersWithValues() valida dentro del closure de
        // array_filter, pero array_filter devuelve los valores originales), asi
        // que la whitelist va a mano.
        $tipos = array_values(array_intersect(
            (array) ($this->getAppliedFilterWithValue('tipo') ?? []),
            ReparacionTipo::values()
        ));

        if ($tipos !== []) {
            $query->whereIn('productos_reparaciones.tipo', $tipos);
        }

        $estado = $this->getAppliedFilterWithValue('estado');

        if (in_array($estado, ['Pendiente', 'Terminado'], true)) {
            $query->where('productos_reparaciones.estado', $estado);
        }

        return $query;
    }

    /**
     * Subconsulta escalar y NO withSum('repuestos', 'cantidad'), por tres cosas:
     *   - withAggregate() inyecta un `select productos_reparaciones.*` cuando
     *     columns es null, y el SELECT acaba con dos items llamados created_at:
     *     con el JOIN de tecnicos eso es el caso tipico de "column in order
     *     clause is ambiguous".
     *   - no aplica COALESCE, asi que una reparacion sin lineas devuelve NULL.
     *   - su alias no se puede declarar como Column normal: selectFields() lo
     *     cualificaria a productos_reparaciones.repuestos_sum_cantidad -> 1054.
     *
     * Es ademas el idioma que ya usa ProductoRepuestosTable para cobrado_en_venta.
     */
    public function builder(): Builder
    {
        return $this->scopedQuery()
            ->addSelect([
                'unidades_repuestos' => ProductoReparacionRepuesto::query()
                    ->selectRaw('COALESCE(SUM(cantidad), 0)')
                    ->whereColumn(
                        'productos_reparaciones_repuestos.producto_reparacion_id',
                        'productos_reparaciones.id'
                    ),
            ]);
    }

    /**
     * Totales de TODAS las filas filtradas, no solo de la pagina visible: el
     * footer nativo recibe $this->getRows, que es el paginador de la pagina
     * actual (components/table/tr/footer.blade.php).
     *
     * toBase() para que vuelva un stdClass en vez de hidratar un
     * ProductoReparacion fantasma sin id, y para poder colgarle las unidades.
     *
     * Las unidades salen de una SEGUNDA consulta y no de un join a la tabla
     * hija: ese join duplica cada reparacion por su numero de lineas y
     * multiplicaria TODOS los SUM() de dinero.
     */
    protected function getTotales(): object
    {
        if ($this->totales !== null) {
            return $this->totales;
        }

        $totales = $this->scopedQuery()
            ->toBase()
            ->selectRaw('COUNT(*) as filas')
            ->selectRaw('COALESCE(SUM(costo), 0) as mano_obra')
            ->selectRaw('COALESCE(SUM(costo_repuestos), 0) as repuestos')
            ->selectRaw('COALESCE(SUM(costo_total), 0) as total')
            ->first();

        $totales->unidades = (int) ProductoReparacionRepuesto::query()
            ->whereIn(
                'productos_reparaciones_repuestos.producto_reparacion_id',
                $this->scopedQuery()->select('productos_reparaciones.id')
            )
            ->sum('cantidad');

        return $this->totales = $totales;
    }

    public function filters(): array
    {
        // Sin ->filter(): applyFilters() ignora los filtros sin callback
        // (hasFilterCallback() === false) y la condicion se aplica en
        // scopedQuery() para que el pie cuadre. Las pills, el badge de conteo y
        // el query string siguen funcionando igual.
        return [
            MultiSelectDropdownFilter::make('Tipo', 'tipo')
                ->options(ReparacionTipo::toSelectArray()->toArray()),

            // La opcion vacia va explicita: SelectFilter renderiza exactamente
            // las opciones que se le pasan, sin agregar un "Todos". Combinar la
            // falta de esa opcion con un setFilterDefaultValue es lo que dejo a
            // TecnicoProductoTable sin forma de ver todo.
            SelectFilter::make('Estado', 'estado')
                ->options([
                    '' => 'Todos',
                    'Pendiente' => 'Pendientes',
                    'Terminado' => 'Terminados',
                ]),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('#', 'id')
                ->sortable()
                ->format(fn($value) => '#' . $value)
                ->footer(fn($rows) => 'TOTALES'),

            Column::make('Fecha', 'created_at')
                // Callback en vez de sortable() a secas: sin desempate por id,
                // dos reparaciones del mismo segundo pueden saltar de pagina.
                // applySorting() hace setBuilder(call_user_func(...)) con tipo
                // Builder => el callback DEBE devolver el builder.
                ->sortable(fn(Builder $query, string $direction) => $query
                    ->orderBy('productos_reparaciones.created_at', $direction)
                    ->orderBy('productos_reparaciones.id', $direction))
                ->format(fn($value) => $value ? Carbon::parse($value)->format('d/m/Y') : '—')
                ->footer(fn($rows) => (int) $this->getTotales()->filas . ' reparaciones'),

            Column::make('Tipo', 'tipo')
                ->sortable()
                ->format(function ($value) {
                    $tipo = ReparacionTipo::tryFrom((string) $value);

                    return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium '
                        . ($tipo?->badgeClasses() ?? 'bg-gray-100 text-gray-800') . '">'
                        . e($tipo?->label() ?? $value) . '</span>';
                })
                ->html(),

            Column::make('Estado', 'estado')
                ->sortable()
                ->format(function ($value) {
                    $classes = $value === 'Terminado'
                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                        : 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200';

                    return '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold '
                        . $classes . '">' . e($value) . '</span>';
                })
                ->html(),

            // El punto une la relacion solo, sin tocar el builder. El ?-> no
            // hace falta aqui porque el LEFT JOIN devuelve NULL, no revienta,
            // pero tecnico_id es nullable (FK onDelete set null).
            Column::make('Técnico', 'tecnico.nombre')
                ->sortable()
                ->format(fn($value) => $value ?: 'Sin técnico')
                ->collapseOnTablet(),

            // Columna LABEL y no Column con campo: el agregado vive solo como
            // alias del SELECT y selectFields() cualificaria un campo normal a
            // productos_reparaciones.unidades_repuestos.
            //
            // El (int) va DENTRO del label: getContents() retorna antes de
            // aplicar format() cuando la columna es label, asi que un ->format()
            // aqui se ignoraria en silencio.
            //
            // "inv." a proposito: cuenta solo los repuestos DE INVENTARIO. Los
            // del tecnico van en los textarea de texto libre y su importe puede
            // estar cargado a mano en costo_repuestos, asi que un "0" con
            // Repuestos (Bs) > 0 es legitimo y no un dato corrupto.
            Column::make('Repuestos inv. (u.)')
                ->label(fn($row) => (int) ($row->unidades_repuestos ?? 0))
                // isSortable() es hasField() && $sortable, y una label no tiene
                // field: el paquete la ordena por su slug (th.blade.php ->
                // getColumnSortKey()). Slug fijo para que la clave del query
                // string no dependa del titulo.
                ->setCustomSlug('unidades-repuestos')
                ->sortable(fn(Builder $query, string $direction) => $query
                    ->orderBy('unidades_repuestos', $direction))
                ->footer(fn($rows) => (string) $this->getTotales()->unidades),

            Column::make('Mano de obra (Bs)', 'costo')
                ->sortable()
                ->format(fn($value) => 'Bs. ' . number_format((float) $value, 2))
                ->footer(fn($rows) => 'Bs. ' . number_format((float) $this->getTotales()->mano_obra, 2)),

            // Valor guardado, sin recalcular: mismo criterio que el bloque de
            // detalle ("no se recalcula nada"). ProductoEstadoModal lo
            // sobrescribe desde las lineas de inventario, pero tambien es
            // editable a mano en los modales de reparacion.
            Column::make('Repuestos (Bs)', 'costo_repuestos')
                ->sortable()
                ->format(fn($value) => 'Bs. ' . number_format((float) $value, 2))
                ->footer(fn($rows) => 'Bs. ' . number_format((float) $this->getTotales()->repuestos, 2)),

            // SIN collapse*: la celda del pie hereda el `hidden lg:table-cell`
            // de la columna (td/plain.blade.php) y la fila de totales no tiene
            // boton para desplegarse, asi que el total desapareceria por debajo
            // de lg. Es el numero por el que se abre esta pestana.
            Column::make('Total (Bs)', 'costo_total')
                ->sortable()
                ->format(fn($value) => 'Bs. ' . number_format((float) $value, 2))
                ->footer(fn($rows) => 'Bs. ' . number_format((float) $this->getTotales()->total, 2)),

            // Sin total al pie: mezclar un cobro al cliente con las columnas de
            // costo en la misma fila de totales invita a sumarlos.
            Column::make('Cobro cliente (Bs)', 'cobro_cliente')
                ->sortable()
                ->format(fn($value) => (float) $value > 0
                    ? 'Bs. ' . number_format((float) $value, 2)
                    : '—')
                ->collapseOnTablet(),

            Column::make('Acciones', 'id')
                ->format(fn($value, $row) => view(
                    'livewire.producto-historial.reparaciones-actions-buttons',
                    ['id' => $row->id]
                )),
        ];
    }

    /**
     * Reusa el modal de la pantalla de tecnicos: ya hace find($id) y el load()
     * de las relaciones que x-reparacion-detalle necesita.
     */
    public function verReparacion($id)
    {
        $this->dispatch('openReparacionShowModal', $id);
    }
}
