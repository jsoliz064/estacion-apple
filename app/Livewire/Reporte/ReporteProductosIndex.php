<?php

namespace App\Livewire\Reporte;

use App\Enums\LineaTipo;
use App\Enums\ProductoEstado;
use App\Traits\ReporteFiltrosTrait;
use App\Traits\ReportePeriodoTrait;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Reporte de productos (docs/09): que equipos se mueven y cuales no.
 *
 *  - POR MODELO (modelo + almacenamiento), sobre los equipos vendidos en el
 *    periodo: unidades, ingreso neto (descuento prorrateado), costo, ganancia,
 *    margen, dias promedio en venderse y cuantos quedan hoy. Ordenable: cubre
 *    "mas vendidos", "ganancia por modelo" y "rotacion".
 *  - PARADOS: los disponibles que llevan N dias o mas desde que entraron.
 *
 * La ENTRADA de un equipo es la fecha de su compra (compras.fecha, que puede
 * cargarse con fecha anterior) o, si vino en permuta y no tiene compra, su alta
 * (productos.created_at).
 */
class ReporteProductosIndex extends Component
{
    use ReporteFiltrosTrait;
    use ReportePeriodoTrait {
        mes as traitMes;
    }
    use WithPagination;

    public const ORDENES = ['unidades' => 'Más vendidos', 'ganancia' => 'Más ganancia', 'margen' => 'Mejor margen', 'dias' => 'Más lentos'];
    public const DIAS_PARADO = [30, 60, 90, 180];

    private const ENTRADA = 'COALESCE(c.fecha, DATE(p.created_at))';

    public string $orden = 'unidades';
    public int $diasParado = 60;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('reporte.productos'), 403);
        $this->iniciarPeriodo();
    }

    public function updated($propiedad): void
    {
        if (in_array($propiedad, ['sucursalId', 'diasParado'], true)) {
            $this->resetPage('parados');
        }
    }

    public function mes(int $delta): void
    {
        $this->traitMes($delta);
    }

    private function porModelo()
    {
        [$desde, $hasta] = $this->periodo();

        $filas = $this->porSucursal(DB::table('ventas_detalles as d')
            ->join('ventas as v', 'v.id', '=', 'd.venta_id')
            ->join('productos as p', 'p.id', '=', 'd.producto_id')
            ->leftJoin('productos_modelos as m', 'm.id', '=', 'p.producto_modelo_id')
            ->leftJoin('compras_detalles as cd', 'cd.producto_id', '=', 'p.id')
            ->leftJoin('compras as c', 'c.id', '=', 'cd.compra_id')
            ->where('d.tipo', LineaTipo::Producto->value)
            ->whereBetween('v.created_at', [$desde, $hasta]), 'v.sucursal_id')
            ->groupBy('p.producto_modelo_id', 'm.nombre', 'p.almacenamiento')
            ->selectRaw('p.producto_modelo_id, m.nombre AS modelo, p.almacenamiento,
                COUNT(*) AS unidades,
                ' . self::INGRESO_NETO . ' AS ingreso,
                SUM(d.subtotal_costo) AS costo,
                AVG(DATEDIFF(v.created_at, ' . self::ENTRADA . ')) AS dias')
            ->get();

        $enStock = $this->porSucursal(DB::table('productos as p')
            ->where('p.estado', ProductoEstado::Inventario->value)
            ->whereNull('p.dado_de_baja_at'), 'p.sucursal_id')
            ->groupBy('p.producto_modelo_id', 'p.almacenamiento')
            ->selectRaw('p.producto_modelo_id, p.almacenamiento, COUNT(*) AS cantidad')
            ->get()
            ->keyBy(fn($f) => $f->producto_modelo_id . '|' . $f->almacenamiento);

        $filas = $filas->map(function ($f) use ($enStock) {
            $ingreso = round((float) $f->ingreso, 2);
            $costo = round((float) $f->costo, 2);

            return (object) [
                'modelo' => trim(($f->modelo ?? 'Sin modelo') . ' ' . $f->almacenamiento),
                'unidades' => (int) $f->unidades,
                'ingreso' => $ingreso,
                'costo' => $costo,
                'ganancia' => round($ingreso - $costo, 2),
                'margen' => $ingreso > 0 ? round(($ingreso - $costo) / $ingreso * 100, 1) : 0.0,
                'dias' => $f->dias !== null ? (int) round((float) $f->dias) : null,
                'en_stock' => (int) ($enStock[$f->producto_modelo_id . '|' . $f->almacenamiento]->cantidad ?? 0),
            ];
        });

        $orden = array_key_exists($this->orden, self::ORDENES) ? $this->orden : 'unidades';

        return $filas->sortByDesc(fn($f) => [$f->{$orden} ?? -1, $f->unidades])->values();
    }

    private function parados()
    {
        $dias = in_array($this->diasParado, self::DIAS_PARADO, true) ? $this->diasParado : 60;

        $query = $this->porSucursal(DB::table('productos as p')
            ->leftJoin('productos_modelos as m', 'm.id', '=', 'p.producto_modelo_id')
            ->leftJoin('sucursales as s', 's.id', '=', 'p.sucursal_id')
            ->leftJoin('compras_detalles as cd', 'cd.producto_id', '=', 'p.id')
            ->leftJoin('compras as c', 'c.id', '=', 'cd.compra_id')
            ->where('p.estado', ProductoEstado::Inventario->value)
            ->whereNull('p.dado_de_baja_at')
            ->whereRaw('DATEDIFF(CURDATE(), ' . self::ENTRADA . ') >= ?', [$dias]), 'p.sucursal_id');

        $totales = (clone $query)->selectRaw('COUNT(*) AS cantidad, COALESCE(SUM(p.costo_total), 0) AS costo')->first();

        $lista = $query
            ->selectRaw('p.id, p.imei, p.almacenamiento, p.color, p.estado_grado, p.costo_total, p.precio_cliente,
                m.nombre AS modelo, s.nombre AS sucursal, DATEDIFF(CURDATE(), ' . self::ENTRADA . ') AS dias')
            ->orderByDesc('dias')
            ->orderBy('p.id')
            ->paginate(20, ['*'], 'parados');

        return [$lista, $totales];
    }

    public function render()
    {
        [$parados, $totalParados] = $this->parados();

        return view('livewire.reporte.reporte-productos-index', [
            'modelos' => $this->porModelo(),
            'parados' => $parados,
            'totalParados' => $totalParados,
        ]);
    }
}
