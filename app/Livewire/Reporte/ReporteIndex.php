<?php

namespace App\Livewire\Reporte;

use App\Enums\LineaTipo;
use App\Traits\ReporteFiltrosTrait;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Reporte por linea de negocio.
 *
 * La pantalla tiene DOS niveles y esa es toda su estructura:
 *
 *  1. Un resumen general FIJO con el total del negocio. No depende de la
 *     pestana, y ahi vive la mano de obra, que no pertenece a ningun articulo.
 *  2. Tres pestanas simetricas -- Celulares, Repuestos, Accesorios -- donde
 *     cada una responde lo mismo sobre su linea: que se vendio, que se compro
 *     y cuanto se gano.
 *
 * Todo en Bs y sobre la venta y la compra unificadas: cada pestana es el
 * mismo juego de consultas sobre ventas_detalles / compras_detalles filtrado
 * por la columna generada `tipo` (LineaTipo).
 */
class ReporteIndex extends Component
{
    use ReporteFiltrosTrait;
    use WithPagination;

    public const TABS = [
        'celulares' => 'Celulares',
        'repuestos' => 'Repuestos',
        'accesorios' => 'Accesorios',
    ];

    /** Umbral a partir del cual agrupar por dia deja de ser legible. */
    private const DIAS_AVISO_AGRUPACION = 90;

    // DESCUENTO_PRORRATEADO e INGRESO_NETO vienen de ReporteFiltrosTrait: los
    // reportes de vendedores y de productos tienen que cuadrar con este.

    public $startDate;
    public $endDate;
    public $reportType = 'daily';
    public $chartMetric = 'totales';

    /** Linea de negocio activa. Unico mando de la pantalla. */
    public $tab = 'celulares';

    /** Se construye entero en loadChartData(). */
    public $chartData = ['labels' => [], 'datasets' => []];

    public function mount()
    {
        $this->endDate = Carbon::today()->format('Y-m-d');
        $this->startDate = Carbon::today()->startOfMonth()->format('Y-m-d');
        $this->loadChartData();
    }

    /**
     * Se precalcula TODO aqui y se pasa a la vista en un array. El blade solia
     * llamar $this->getXxx() unas 16 veces por render, cada una con sus
     * consultas; con pestanas y tarjetas nuevas eso se multiplicaba.
     */
    public function render()
    {
        return view('livewire.reporte.reporte-index', [
            'general' => $this->resumenGeneral(),
            'resumen' => $this->resumenPestana(),
            'tablaData' => $this->tablaData(),
            'avisoAgrupacion' => $this->avisoAgrupacion(),
        ]);
    }

    public function seleccionarTab(string $tab): void
    {
        if (!array_key_exists($tab, self::TABS)) {
            return;
        }

        $this->tab = $tab;
        $this->resetPage();
        $this->loadChartData();
    }

    public function updatedStartDate($value)
    {
        $this->resetPage();
        if (strtotime($value) > strtotime($this->endDate)) {
            session()->flash('date_error', 'La fecha de inicio no puede ser mayor que la fecha de fin');
            return;
        }
        $this->validateDates();
        $this->loadChartData();
    }

    public function updatedEndDate()
    {
        $this->resetPage();
        $this->validateDates();
        $this->loadChartData();
    }

    public function updatedSucursalId()
    {
        $this->resetPage();
        $this->loadChartData();
    }

    public function updatedReportType()
    {
        $this->resetPage();
        $this->loadChartData();
    }

    public function updatedChartMetric()
    {
        $this->loadChartData();
    }

    public function validateDates()
    {
        try {
            $this->validate([
                'startDate' => [
                    'required',
                    'date_format:Y-m-d',
                    'before_or_equal:endDate',
                    'after_or_equal:2024-01-01',
                    'before_or_equal:today'
                ],
                'endDate' => [
                    'required',
                    'date_format:Y-m-d',
                    'after_or_equal:startDate',
                    'after_or_equal:2024-01-01',
                    'before_or_equal:today'
                ]
            ], [
                // Una sola entrada por regla: antes habia dos `before_or_equal`
                // de startDate y dos `after_or_equal` de endDate, y la segunda
                // pisaba a la primera en silencio.
                'startDate.required' => 'La fecha de inicio es obligatoria',
                'startDate.date_format' => 'El formato de la fecha de inicio es inválido (AAAA-MM-DD)',
                'startDate.before_or_equal' => 'La fecha de inicio no puede ser futura ni posterior a la fecha final',
                'startDate.after_or_equal' => 'La fecha de inicio no puede ser anterior al 01/01/2024',

                'endDate.required' => 'La fecha final es obligatoria',
                'endDate.date_format' => 'El formato de la fecha final es inválido (AAAA-MM-DD)',
                'endDate.after_or_equal' => 'La fecha final debe ser posterior a la de inicio y no anterior al 01/01/2024',
                'endDate.before_or_equal' => 'La fecha final no puede ser futura'
            ], [
                'startDate' => 'fecha de inicio',
                'endDate' => 'fecha final'
            ]);

            if (!checkdate(
                (int)Carbon::parse($this->startDate)->format('m'),
                (int)Carbon::parse($this->startDate)->format('d'),
                (int)Carbon::parse($this->startDate)->format('Y')
            )) {
                throw ValidationException::withMessages([
                    'startDate' => 'La fecha de inicio no es una fecha válida'
                ]);
            }

            if (!checkdate(
                (int)Carbon::parse($this->endDate)->format('m'),
                (int)Carbon::parse($this->endDate)->format('d'),
                (int)Carbon::parse($this->endDate)->format('Y')
            )) {
                throw ValidationException::withMessages([
                    'endDate' => 'La fecha final no es una fecha válida'
                ]);
            }

            session()->forget(['date_error', 'date_info']);

            if (Carbon::parse($this->startDate)->greaterThan(Carbon::parse($this->endDate))) {
                $tempDate = $this->startDate;
                $this->startDate = $this->endDate;
                $this->endDate = $tempDate;
                session()->flash('date_info', 'Las fechas se ajustaron automáticamente para mostrar el rango correcto');
            }
        } catch (ValidationException $e) {
            session()->flash('date_error', 'Por favor ingrese fechas válidas');
            throw $e;
        }
    }

    // ==================================================================
    // Rango y utilidades comunes
    // ==================================================================

    private function rangoActual(): array
    {
        return [
            Carbon::parse($this->startDate)->startOfDay(),
            Carbon::parse($this->endDate)->endOfDay(),
        ];
    }

    private function periodoAnterior(): array
    {
        $largo = $this->getPeriodLength();

        return [
            Carbon::parse($this->startDate)->subDays($largo)->startOfDay(),
            Carbon::parse($this->endDate)->subDays($largo)->endOfDay(),
        ];
    }

    private function getPeriodLength(): int
    {
        return Carbon::parse($this->startDate)->diffInDays(Carbon::parse($this->endDate)) + 1;
    }

    private function variacion(float $actual, float $anterior): float
    {
        return $anterior != 0 ? round(($actual - $anterior) / $anterior * 100, 2) : 0;
    }

    /** Division protegida: un periodo sin ventas no debe reventar el reporte. */
    private function ratio(float $numerador, float $denominador): float
    {
        return $denominador != 0 ? round($numerador / $denominador, 2) : 0;
    }

    /** El tipo de linea (ventas_detalles.tipo / compras_detalles.tipo) de la pestana. */
    private function tipoTab(): string
    {
        return match ($this->tab) {
            'repuestos' => LineaTipo::Repuesto->value,
            'accesorios' => LineaTipo::Accesorio->value,
            default => LineaTipo::Producto->value,
        };
    }

    private function esCelulares(): bool
    {
        return $this->tipoTab() === LineaTipo::Producto->value;
    }

    private function avisoAgrupacion(): ?string
    {
        if ($this->reportType === 'daily' && $this->getPeriodLength() > self::DIAS_AVISO_AGRUPACION) {
            return 'El rango abarca ' . $this->getPeriodLength() . ' días. Agrupa por mes para que el gráfico sea legible.';
        }

        return null;
    }

    // ==================================================================
    // Agregados: UNA familia de consultas, filtrada por el tipo de la linea
    // ==================================================================

    /**
     * Las lineas de venta del periodo. Se filtra por `ventas.created_at` (la
     * fecha de la venta) y por `d.tipo`, la columna generada desde las FK: no
     * puede contradecir al articulo de la linea.
     */
    private function lineasVenta($desde, $hasta, ?string $tipo = null): Builder
    {
        return $this->porSucursal(DB::table('ventas_detalles as d')
            ->join('ventas as v', 'v.id', '=', 'd.venta_id')
            // Un regalo no es una venta del accesorio: no suma unidades ni ranking
            // (su dinero es 0 y su costo ya va en la linea del equipo).
            ->whereNull('d.producto_regalo_id')
            ->whereBetween('v.created_at', [$desde, $hasta])
            ->when($tipo, fn($q) => $q->where('d.tipo', $tipo)), 'v.sucursal_id');
    }

    /**
     * Las lineas de compra del periodo, por la fecha de la compra. Sin los
     * borradores: todavia no entraron al inventario.
     */
    private function lineasCompra($desde, $hasta, ?string $tipo = null): Builder
    {
        return $this->porSucursal(DB::table('compras_detalles as d')
            ->join('compras as c', 'c.id', '=', 'd.compra_id')
            ->whereNotNull('c.finalizada_at')
            ->whereBetween('c.fecha', [$desde, $hasta])
            ->when($tipo, fn($q) => $q->where('d.tipo', $tipo)), 'c.sucursal_id');
    }

    /**
     * Ventas por tipo de linea, con el descuento de cabecera prorrateado entre
     * TODAS las lineas de la venta (equipos, repuestos y accesorios).
     *
     * Sin el viejo respaldo del 20% para celulares con costo 0: existia por
     * datos de la importadora capturados sin costo. Aqui la linea congela el
     * costo_total del equipo al vender, asi que un costo 0 es un dato real (o
     * un error que debe verse), no algo que inventar.
     *
     * Los cobros de repuestos de una reparacion son lineas tipo Repuesto con
     * costo 0 a proposito: la pieza ya viaja dentro del costo del equipo.
     */
    private function agregadoVentas($desde, $hasta, ?string $tipo = null): object
    {
        $fila = $this->lineasVenta($desde, $hasta, $tipo)
            ->selectRaw('
                COALESCE(SUM(d.subtotal), 0) AS bruto,
                COALESCE(' . self::DESCUENTO_PRORRATEADO . ', 0) AS descuento,
                COALESCE(SUM(d.subtotal_costo), 0) AS costo,
                COALESCE(SUM(d.cantidad), 0) AS unidades,
                COUNT(DISTINCT v.id) AS operaciones')
            ->first();

        $ingreso = round((float) $fila->bruto - (float) $fila->descuento, 2);
        $costo = round((float) $fila->costo, 2);

        return (object) [
            'ingreso' => $ingreso,
            'costo' => $costo,
            'unidades' => (int) $fila->unidades,
            'operaciones' => (int) $fila->operaciones,
            'ganancia' => round($ingreso - $costo, 2),
        ];
    }

    private function agregadoCompras($desde, $hasta, ?string $tipo = null): object
    {
        $fila = $this->lineasCompra($desde, $hasta, $tipo)
            ->selectRaw('
                COALESCE(SUM(d.subtotal), 0) AS costo,
                COALESCE(SUM(d.cantidad), 0) AS unidades,
                COUNT(DISTINCT c.id) AS operaciones')
            ->first();

        return (object) [
            'costo' => round((float) $fila->costo, 2),
            'unidades' => (int) $fila->unidades,
            'operaciones' => (int) $fila->operaciones,
        ];
    }

    /**
     * Perdidas del periodo: equipos dados de baja (a su costo_total) y unidades
     * de stock dadas de baja (al costo congelado en stock_bajas). Ninguna pasa
     * por una venta, asi que sin esta tarjeta serian invisibles en el reporte.
     */
    private function perdidas($desde, $hasta, ?string $tipo = null): float
    {
        $total = 0.0;

        if ($tipo === null || $tipo === LineaTipo::Producto->value) {
            $total += (float) $this->porSucursal(DB::table('productos')
                ->whereBetween('dado_de_baja_at', [$desde, $hasta]), 'sucursal_id')
                ->sum('costo_total');
        }

        $articulo = $tipo ? LineaTipo::from($tipo)->articulo() : null;

        if ($tipo === null || $articulo) {
            $total += (float) $this->porSucursal(DB::table('stock_bajas')
                ->whereBetween('created_at', [$desde, $hasta])
                ->when($articulo, fn($q) => $q->whereNotNull($articulo->columna())), 'sucursal_id')
                ->sum(DB::raw('cantidad * costo'));
        }

        return round($total, 2);
    }

    /**
     * Mano de obra del periodo. No pertenece a ningun articulo, asi que no se
     * reparte entre lineas ni cabe en ninguna pestana: es la razon de que el
     * resumen general exista. Suma al ingreso Y al costo de la venta, por lo
     * que se cancela en la ganancia y el total sigue cuadrando con ventas.total.
     */
    private function manoObra($desde, $hasta): float
    {
        return round((float) $this->porSucursal(DB::table('ventas')
            ->whereBetween('created_at', [$desde, $hasta]), 'sucursal_id')
            ->sum('mano_obra'), 2);
    }

    // ==================================================================
    // Resumen general: todo el negocio, independiente de la pestana
    // ==================================================================

    private function resumenGeneral(): array
    {
        [$desde, $hasta] = $this->rangoActual();
        [$desdeAnt, $hastaAnt] = $this->periodoAnterior();

        $act = $this->totalesNegocio($desde, $hasta);
        $ant = $this->totalesNegocio($desdeAnt, $hastaAnt);

        return array_merge($act, [
            'margen' => $this->ratio($act['ganancia'] * 100, $act['ingreso']),
            'ticket' => $this->ratio($act['ingreso'], $act['operaciones']),
            'tendenciaIngreso' => $this->variacion($act['ingreso'], $ant['ingreso']),
            'tendenciaGanancia' => $this->variacion($act['ganancia'], $ant['ganancia']),
            'tendenciaInversion' => $this->variacion($act['inversion'], $ant['inversion']),
        ]);
    }

    private function totalesNegocio($desde, $hasta): array
    {
        $porTipo = [];
        foreach (LineaTipo::cases() as $caso) {
            $porTipo[$caso->value] = $this->agregadoVentas($desde, $hasta, $caso->value);
        }

        $todo = $this->agregadoVentas($desde, $hasta);
        $compras = $this->agregadoCompras($desde, $hasta);
        $mo = $this->manoObra($desde, $hasta);

        // Las operaciones se cuentan sobre TODAS las lineas a la vez: una venta
        // con un equipo y un cargador es UNA operacion, no dos.
        return [
            'ingreso' => round($todo->ingreso + $mo, 2),
            // La mano de obra se cancela: entra en el ingreso y en el costo.
            'ganancia' => $todo->ganancia,
            'inversion' => $compras->costo,
            'perdidas' => $this->perdidas($desde, $hasta),
            'manoObra' => $mo,
            'unidades' => $todo->unidades,
            'unidadesCompradas' => $compras->unidades,
            'operaciones' => $todo->operaciones,
            'desglose' => [
                ['etiqueta' => 'Celulares', 'monto' => $porTipo[LineaTipo::Producto->value]->ingreso],
                ['etiqueta' => 'Repuestos', 'monto' => $porTipo[LineaTipo::Repuesto->value]->ingreso],
                ['etiqueta' => 'Accesorios', 'monto' => $porTipo[LineaTipo::Accesorio->value]->ingreso],
                ['etiqueta' => 'Mano de obra', 'monto' => $mo],
            ],
        ];
    }

    // ==================================================================
    // Resumen de la pestana activa: una linea de negocio
    // ==================================================================

    /**
     * Las tres pestanas devuelven EXACTAMENTE las mismas claves y salen de las
     * mismas consultas; solo cambia el tipo de linea.
     */
    private function resumenPestana(): array
    {
        [$desde, $hasta] = $this->rangoActual();
        [$desdeAnt, $hastaAnt] = $this->periodoAnterior();

        $etiqueta = self::TABS[$this->tab];
        $tipo = $this->tipoTab();

        $ventas = $this->agregadoVentas($desde, $hasta, $tipo);
        $ventasAnt = $this->agregadoVentas($desdeAnt, $hastaAnt, $tipo);
        $compras = $this->agregadoCompras($desde, $hasta, $tipo);
        $comprasAnt = $this->agregadoCompras($desdeAnt, $hastaAnt, $tipo);

        $descripcion = 'Solo ' . mb_strtolower($etiqueta) . ': lo vendido, lo comprado y la ganancia del período. '
            . 'El descuento de cada venta se reparte a prorrata entre todas sus líneas; la mano de obra está en el resumen de arriba.';

        return [
            'titulo' => $etiqueta,
            'descripcion' => $descripcion,
            'ingreso' => $ventas->ingreso,
            'costo' => $ventas->costo,
            'ganancia' => $ventas->ganancia,
            'unidades' => $ventas->unidades,
            'operaciones' => $ventas->operaciones,
            'inversion' => $compras->costo,
            'unidadesCompradas' => $compras->unidades,
            'perdidas' => $this->perdidas($desde, $hasta, $tipo),
            'costoPromedio' => $this->ratio($compras->costo, $compras->unidades),
            'margen' => $this->ratio($ventas->ganancia * 100, $ventas->ingreso),
            'ticket' => $this->ratio($ventas->ingreso, $ventas->operaciones),
            'tendenciaIngreso' => $this->variacion($ventas->ingreso, $ventasAnt->ingreso),
            'tendenciaGanancia' => $this->variacion($ventas->ganancia, $ventasAnt->ganancia),
            'tendenciaInversion' => $this->variacion($compras->costo, $comprasAnt->costo),
            'mejorVendedor' => $this->mejorPor('user'),
            'mejorSucursal' => $this->mejorPor('sucursal'),
            'topArticulos' => $this->topArticulos(),
            'porTipoVenta' => $this->esCelulares() ? $this->porTipoVenta($desde, $hasta) : [],
        ];
    }

    /**
     * Celulares por tipo de venta (Venta / Oferta / Venta externa), con el
     * valor CONGELADO en la linea: editar el equipo despues no reescribe el
     * reporte.
     */
    private function porTipoVenta($desde, $hasta): array
    {
        return $this->lineasVenta($desde, $hasta, LineaTipo::Producto->value)
            ->groupBy('d.tipo_venta')
            ->orderByDesc('monto')
            ->select(
                DB::raw("COALESCE(d.tipo_venta, 'Venta') as tipo_venta"),
                DB::raw('COUNT(*) as unidades'),
                DB::raw('SUM(d.subtotal) - ' . self::DESCUENTO_PRORRATEADO . ' as monto')
            )
            ->get()
            ->all();
    }

    // ==================================================================
    // Rankings
    // ==================================================================

    /** Mejor vendedor o mejor sucursal de la linea activa, por ingreso neto. */
    private function mejorPor(string $eje): ?object
    {
        $columna = $eje === 'user' ? 'user_id' : 'sucursal_id';
        $tabla = $eje === 'user' ? 'users' : 'sucursales';
        $campo = $eje === 'user' ? 'name' : 'nombre';

        [$desde, $hasta] = $this->rangoActual();

        $fila = $this->lineasVenta($desde, $hasta, $this->tipoTab())
            ->join("$tabla as t", 't.id', '=', "v.$columna")
            ->groupBy('t.id', "t.$campo")
            ->select("t.$campo as nombre", DB::raw('SUM(d.subtotal) - ' . self::DESCUENTO_PRORRATEADO . ' as monto'))
            ->orderByDesc('monto')
            ->first();

        return $fila ? (object) ['nombre' => $fila->nombre, 'monto' => round((float) $fila->monto, 2)] : null;
    }

    /**
     * Top de articulos de la linea activa. Un celular es una unidad unica con
     * IMEI, asi que ahi se agrupa por MODELO; repuestos y accesorios, por
     * articulo.
     */
    private function topArticulos(int $limite = 5): array
    {
        [$desde, $hasta] = $this->rangoActual();
        $query = $this->lineasVenta($desde, $hasta, $this->tipoTab());

        if ($this->esCelulares()) {
            $query->join('productos as p', 'p.id', '=', 'd.producto_id')
                ->leftJoin('productos_modelos as m', 'm.id', '=', 'p.producto_modelo_id')
                ->groupBy('m.nombre')
                ->select(DB::raw("COALESCE(m.nombre, 'Sin modelo') as nombre"));
        } else {
            // ArticuloTipo es la unica fuente de los nombres de tabla y columna
            // que se interpolan.
            $articulo = LineaTipo::from($this->tipoTab())->articulo();
            $query->join($articulo->tabla() . ' as a', 'a.id', '=', 'd.' . $articulo->columna())
                ->groupBy('a.id', 'a.nombre')
                ->select('a.nombre');
        }

        return $query
            ->addSelect(DB::raw('SUM(d.cantidad) as unidades'), DB::raw('SUM(d.subtotal) as monto'))
            ->orderByDesc('unidades')
            ->limit($limite)
            ->get()
            ->all();
    }

    // ==================================================================
    // Tabla "Resumen Analitico"
    // ==================================================================

    /**
     * Misma tabla para las tres pestanas: por periodo, lo vendido y lo comprado
     * de esa linea. Las dos ramas del UNION emiten las MISMAS columnas en el
     * MISMO orden -- un UNION empareja por POSICION, no por nombre.
     */
    private function tablaData()
    {
        [$desde, $hasta] = $this->rangoActual();
        $fmt = $this->reportType === 'monthly' ? '%Y-%m' : '%Y-%m-%d';
        $tipo = $this->tipoTab();

        $ventas = $this->lineasVenta($desde, $hasta, $tipo)
            ->groupBy('period_key')
            ->select(
                DB::raw("DATE_FORMAT(v.created_at, '$fmt') as period_key"),
                DB::raw('SUM(d.cantidad) as unidades'),
                DB::raw(self::INGRESO_NETO . ' as ingreso'),
                DB::raw(self::INGRESO_NETO . ' - SUM(d.subtotal_costo) as ganancia'),
                DB::raw('0 as comp_unidades'),
                DB::raw('0 as inversion')
            );

        $compras = $this->lineasCompra($desde, $hasta, $tipo)
            ->groupBy('period_key')
            ->select(
                DB::raw("DATE_FORMAT(c.fecha, '$fmt') as period_key"),
                DB::raw('0 as unidades'),
                DB::raw('0 as ingreso'),
                DB::raw('0 as ganancia'),
                DB::raw('SUM(d.cantidad) as comp_unidades'),
                DB::raw('SUM(d.subtotal) as inversion')
            );

        $combinada = $ventas->unionAll($compras);

        return DB::table(DB::raw("({$combinada->toSql()}) as t"))
            ->mergeBindings($combinada)
            ->groupBy('period_key')
            ->orderBy('period_key', 'desc')
            ->select(
                'period_key',
                DB::raw('SUM(unidades) as unidades'),
                DB::raw('SUM(ingreso) as ingreso'),
                DB::raw('SUM(ingreso) - SUM(ganancia) as costo'),
                DB::raw('SUM(ganancia) as ganancia'),
                DB::raw('SUM(comp_unidades) as comp_unidades'),
                DB::raw('SUM(inversion) as inversion')
            )
            ->paginate(10);
    }

    // ==================================================================
    // Grafico
    // ==================================================================

    /**
     * Las tres pestanas dibujan las mismas series -- Ventas, Compras, Ganancia
     * -- de su linea. La tarjeta del grafico vive FUERA de los bloques por
     * pestana (un canvas por pestana romperia el JS, que captura
     * getElementById una sola vez), asi que aqui solo cambian los datos.
     */
    public function loadChartData()
    {
        [$desde, $hasta] = $this->rangoActual();

        $fmt = $this->reportType === 'monthly' ? '%Y-%m' : '%Y-%m-%d';
        $fmtEtiqueta = $this->reportType === 'monthly' ? 'M Y' : 'M d, Y';
        $fmtClave = $this->reportType === 'monthly' ? 'Y-m' : 'Y-m-d';

        $ventas = $this->serieVentas($this->tipoTab(), $desde, $hasta, $fmt);
        $compras = $this->serieCompras($this->tipoTab(), $desde, $hasta, $fmt);

        $series = [
            ['label' => 'Ventas', 'rows' => $ventas],
            ['label' => 'Compras', 'rows' => $compras],
            [
                'label' => 'Ganancia',
                'rows' => $ventas,
                'campo' => 'ganancia',
                // El JS oculta en modo "cantidades" los datasets marcados asi.
                // Antes lo hacia por indice fijo (datasets[2]), que con otras
                // series habria escondido la que no era.
                'hideOnCantidades' => true,
            ],
        ];

        $paleta = [
            ['rgba(95, 131, 82, 1)', 'rgba(95, 131, 82, 0.2)'],
            ['rgba(255, 99, 132, 1)', 'rgba(255, 99, 132, 0.2)'],
            ['rgba(54, 162, 235, 1)', 'rgba(54, 162, 235, 0.2)'],
        ];

        $labels = [];
        $periodos = [];
        $cursor = Carbon::parse($this->startDate)->startOfDay();
        $fin = Carbon::parse($this->endDate)->endOfDay();

        while ($cursor <= $fin) {
            $labels[] = $cursor->format($fmtEtiqueta);
            $periodos[] = $cursor->format($fmtClave);
            $this->reportType === 'monthly' ? $cursor->addMonth()->startOfMonth() : $cursor->addDay();
        }

        $datasets = [];
        foreach ($series as $i => $serie) {
            $campo = $serie['campo'] ?? ($this->chartMetric === 'totales' ? 'monto' : 'unidades');
            $datos = [];

            foreach ($periodos as $clave) {
                $fila = $serie['rows']->get($clave);
                $datos[] = round((float) ($fila->$campo ?? 0), 2);
            }

            [$borde, $fondo] = $paleta[$i % count($paleta)];

            $datasets[] = [
                'label' => $serie['label'],
                'data' => $datos,
                'borderColor' => $borde,
                'backgroundColor' => $fondo,
                'tension' => 0.1,
                'fill' => !isset($serie['campo']),
                'hideOnCantidades' => $serie['hideOnCantidades'] ?? false,
            ];
        }

        $this->chartData = ['labels' => $labels, 'datasets' => $datasets];

        $this->dispatch('updateChart', data: [
            'labels' => $labels,
            'datasets' => $datasets,
            'metric' => $this->chartMetric,
        ]);
    }

    private function serieVentas(string $tipo, $desde, $hasta, string $fmt)
    {
        return $this->lineasVenta($desde, $hasta, $tipo)
            ->groupBy('period_key')
            ->select(
                DB::raw("DATE_FORMAT(v.created_at, '$fmt') as period_key"),
                DB::raw(self::INGRESO_NETO . ' as monto'),
                DB::raw('SUM(d.cantidad) as unidades'),
                DB::raw(self::INGRESO_NETO . ' - SUM(d.subtotal_costo) as ganancia')
            )
            ->get()
            ->keyBy('period_key');
    }

    private function serieCompras(string $tipo, $desde, $hasta, string $fmt)
    {
        return $this->lineasCompra($desde, $hasta, $tipo)
            ->groupBy('period_key')
            ->select(
                DB::raw("DATE_FORMAT(c.fecha, '$fmt') as period_key"),
                DB::raw('SUM(d.subtotal) as monto'),
                DB::raw('SUM(d.cantidad) as unidades'),
                DB::raw('0 as ganancia')
            )
            ->get()
            ->keyBy('period_key');
    }
}
