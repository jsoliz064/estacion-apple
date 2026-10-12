<?php

namespace App\Livewire\Reporte;

use App\Enums\ArticuloTipo;
use App\Enums\BajaMotivo;
use App\Enums\ProductoEstado;
use App\Models\Sucursal;
use App\Traits\ReporteFiltrosTrait;
use App\Traits\ReportePeriodoTrait;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Reporte de inventario (docs/09): cuanta plata hay parada y donde. Es una
 * FOTO DE HOY, salvo las perdidas, que van por periodo.
 *
 *  - Valor por sucursal: equipos sin vender (al costo y a precio vendedor; el
 *    roto a su costo, el criterio de ProductoIndex) y repuestos y accesorios
 *    (unidades x costo y x precio, de stock_sucursales).
 *  - Equipos por estado y por grado.
 *  - Perdidas del periodo por motivo. La Devolucion al proveedor NO es
 *    perdida (costo 0, la reemplazo o descuento el proveedor).
 *  - Por agotarse: filas de stock con minimo y la cantidad en el minimo o
 *    por debajo (el mismo criterio que ArticuloDeStockTrait::scopeBajoStock).
 */
class ReporteInventarioIndex extends Component
{
    use ReporteFiltrosTrait;
    use ReportePeriodoTrait;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('reporte.inventario'), 403);
        $this->iniciarPeriodo();
    }

    private function valorPorSucursal()
    {
        // Sin los de una compra en borrador: tampoco sus articulos entraron
        // todavia al stock, y el valor tiene que medir lo mismo en los dos.
        $equipos = $this->porSucursal(DB::table('productos')
            ->whereNull('dado_de_baja_at')
            ->whereNotIn('estado', [...ProductoEstado::vendidos(), ProductoEstado::EnCompra->value]), 'sucursal_id')
            ->groupBy('sucursal_id')
            ->selectRaw('sucursal_id, COUNT(*) AS cantidad, COALESCE(SUM(costo_total), 0) AS costo,
                COALESCE(SUM(CASE WHEN estado = ? THEN costo_total ELSE precio_vendedor END), 0) AS venta', [ProductoEstado::Roto->value])
            ->get()
            ->keyBy('sucursal_id');

        // Solo lo positivo: un descuadre negativo no es plata parada.
        $articulos = $this->porSucursal(DB::table('stock_sucursales as ss')
            ->leftJoin('repuestos as r', 'r.id', '=', 'ss.repuesto_id')
            ->leftJoin('accesorios as a', 'a.id', '=', 'ss.accesorio_id')
            ->where('ss.cantidad', '>', 0), 'ss.sucursal_id')
            ->groupBy('ss.sucursal_id')
            ->selectRaw('ss.sucursal_id, SUM(ss.cantidad) AS unidades,
                SUM(ss.cantidad * COALESCE(r.costo, a.costo, 0)) AS costo,
                SUM(ss.cantidad * COALESCE(r.precio, a.precio, 0)) AS venta')
            ->get()
            ->keyBy('sucursal_id');

        $ids = $equipos->keys()->merge($articulos->keys())->unique();
        $nombres = Sucursal::whereKey($ids->filter())->pluck('nombre', 'id');

        return $ids->map(fn($id) => (object) [
            'sucursal' => $id ? ($nombres[$id] ?? '—') : 'Sin sucursal',
            'equipos' => (int) ($equipos[$id]->cantidad ?? 0),
            'equipos_costo' => (float) ($equipos[$id]->costo ?? 0),
            'equipos_venta' => (float) ($equipos[$id]->venta ?? 0),
            'unidades' => (int) ($articulos[$id]->unidades ?? 0),
            'articulos_costo' => (float) ($articulos[$id]->costo ?? 0),
            'articulos_venta' => (float) ($articulos[$id]->venta ?? 0),
        ])->sortBy('sucursal')->values();
    }

    private function porEstado()
    {
        $filas = $this->porSucursal(DB::table('productos')
            ->whereNull('dado_de_baja_at')
            ->where('estado', '!=', ProductoEstado::Vendido->value), 'sucursal_id')
            ->groupBy('estado')
            ->selectRaw('estado, COUNT(*) AS cantidad, COALESCE(SUM(costo_total), 0) AS costo')
            ->get()
            ->keyBy('estado');

        return collect(ProductoEstado::cases())
            ->reject(fn($e) => $e === ProductoEstado::Vendido)
            ->map(fn($e) => (object) [
                'estado' => $e === ProductoEstado::Credito ? $e->label() . ' (ya es del cliente)' : $e->label(),
                'color' => $e->color(),
                'cantidad' => (int) ($filas[$e->value]->cantidad ?? 0),
                'costo' => (float) ($filas[$e->value]->costo ?? 0),
            ])
            ->values();
    }

    private function porGrado()
    {
        return $this->porSucursal(DB::table('productos')
            ->whereNull('dado_de_baja_at')
            ->whereNotIn('estado', ProductoEstado::vendidos()), 'sucursal_id')
            ->groupBy('estado_grado')
            ->selectRaw('estado_grado, COUNT(*) AS cantidad, COALESCE(SUM(costo_total), 0) AS costo')
            ->orderByRaw("FIELD(estado_grado, 'A+', '1', '2', '3') = 0, FIELD(estado_grado, 'A+', '1', '2', '3')")
            ->get()
            ->map(fn($f) => (object) [
                'grado' => $f->estado_grado ?? 'Sin grado',
                'cantidad' => (int) $f->cantidad,
                'costo' => (float) $f->costo,
            ]);
    }

    /** Perdidas del periodo por motivo, y su detalle. */
    private function perdidas(): array
    {
        [$desde, $hasta] = $this->periodo();
        $devolucion = BajaMotivo::Devolucion->value;

        $equipos = $this->porSucursal(DB::table('productos as p')
            ->leftJoin('productos_modelos as m', 'm.id', '=', 'p.producto_modelo_id')
            ->whereBetween('p.dado_de_baja_at', [$desde, $hasta])
            ->where('p.motivo_baja', '!=', $devolucion), 'p.sucursal_id')
            ->orderByDesc('p.dado_de_baja_at')
            ->get(['p.id', 'p.imei', 'p.almacenamiento', 'p.color', 'p.motivo_baja', 'p.costo_total', 'p.dado_de_baja_at', 'm.nombre as modelo']);

        $unidades = $this->porSucursal(DB::table('stock_bajas as b')
            ->leftJoin('repuestos as r', 'r.id', '=', 'b.repuesto_id')
            ->leftJoin('accesorios as a', 'a.id', '=', 'b.accesorio_id')
            ->whereBetween('b.created_at', [$desde, $hasta])
            ->where('b.motivo', '!=', $devolucion), 'b.sucursal_id')
            ->orderByDesc('b.created_at')
            ->get(['b.id', 'b.cantidad', 'b.costo', 'b.motivo', 'b.created_at', 'b.repuesto_id', DB::raw('COALESCE(r.nombre, a.nombre) as nombre')]);

        $porMotivo = collect(BajaMotivo::manuales())->map(fn($m) => (object) [
            'motivo' => $m->label(),
            'equipos' => $equipos->where('motivo_baja', $m->value)->count(),
            'equipos_costo' => (float) $equipos->where('motivo_baja', $m->value)->sum('costo_total'),
            'unidades' => (int) $unidades->where('motivo', $m->value)->sum('cantidad'),
            'unidades_costo' => (float) $unidades->where('motivo', $m->value)->sum(fn($b) => $b->cantidad * $b->costo),
        ])->filter(fn($f) => $f->equipos > 0 || $f->unidades > 0)->values();

        return [$porMotivo, $equipos, $unidades];
    }

    private function porAgotarse()
    {
        return $this->porSucursal(DB::table('stock_sucursales as ss')
            ->join('sucursales as s', 's.id', '=', 'ss.sucursal_id')
            ->leftJoin('repuestos as r', 'r.id', '=', 'ss.repuesto_id')
            ->leftJoin('accesorios as a', 'a.id', '=', 'ss.accesorio_id')
            ->where('ss.minimo', '>', 0)
            ->whereColumn('ss.cantidad', '<=', 'ss.minimo'), 'ss.sucursal_id')
            ->orderByRaw('(ss.minimo - ss.cantidad) DESC')
            ->orderBy('s.nombre')
            ->get(['ss.repuesto_id', 'ss.accesorio_id', 'ss.cantidad', 'ss.minimo', 's.nombre as sucursal',
                DB::raw('COALESCE(r.nombre, a.nombre) as nombre'), DB::raw('COALESCE(r.sku, a.sku) as sku')])
            ->map(fn($f) => (object) [
                'tipo' => $f->repuesto_id ? ArticuloTipo::Repuesto : ArticuloTipo::Accesorio,
                'id' => (int) ($f->repuesto_id ?: $f->accesorio_id),
                'nombre' => $f->nombre,
                'sku' => $f->sku,
                'sucursal' => $f->sucursal,
                'cantidad' => (int) $f->cantidad,
                'minimo' => (int) $f->minimo,
                'faltan' => max(0, (int) $f->minimo - (int) $f->cantidad),
            ]);
    }

    public function render()
    {
        [$porMotivo, $bajasEquipos, $bajasUnidades] = $this->perdidas();

        return view('livewire.reporte.reporte-inventario-index', [
            'valor' => $this->valorPorSucursal(),
            'estados' => $this->porEstado(),
            'grados' => $this->porGrado(),
            'porMotivo' => $porMotivo,
            'bajasEquipos' => $bajasEquipos,
            'bajasUnidades' => $bajasUnidades,
            'agotarse' => $this->porAgotarse(),
        ]);
    }
}
