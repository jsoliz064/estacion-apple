<?php

namespace App\Livewire\Reporte;

use App\Models\User;
use App\Traits\ReporteFiltrosTrait;
use App\Traits\ReportePeriodoTrait;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Reporte por vendedor (docs/09): cuanto vendio cada uno en el periodo, que
 * ganancia genero y cuanta comision le corresponde, con el detalle de sus
 * ventas.
 *
 * Monto es ventas.total (incluye la mano de obra) y ganancia es total -
 * costo_total, la misma que Venta::ganancia() y la base de la comision: la
 * mano de obra suma a los dos y se cancela. La comision sale de `comisiones`
 * (una fila por venta, UNIQUE venta_id: el LEFT JOIN no duplica).
 */
class ReporteVendedoresIndex extends Component
{
    use ReporteFiltrosTrait;
    use ReportePeriodoTrait {
        mes as traitMes;
    }
    use WithPagination;

    /** El vendedor del detalle: su id, 'sin' (ventas sin vendedor) o ''. */
    public string $vendedor = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('reporte.vendedores'), 403);
        $this->iniciarPeriodo();
    }

    public function updated($propiedad): void
    {
        if (in_array($propiedad, ['desde', 'hasta', 'sucursalId'], true)) {
            $this->resetPage('ventas');
        }
    }

    public function mes(int $delta): void
    {
        $this->traitMes($delta);
        $this->resetPage('ventas');
    }

    public function verVendedor(string $clave): void
    {
        $this->vendedor = $this->vendedor === $clave ? '' : $clave;
        $this->resetPage('ventas');
    }

    /** Las ventas del periodo y de la sucursal, con su comision. */
    private function ventas()
    {
        [$desde, $hasta] = $this->periodo();

        return $this->porSucursal(DB::table('ventas as v')
            ->leftJoin('comisiones as c', 'c.venta_id', '=', 'v.id')
            ->whereBetween('v.created_at', [$desde, $hasta]), 'v.sucursal_id');
    }

    private function porVendedor()
    {
        $filas = $this->ventas()
            ->groupBy('v.user_id')
            ->selectRaw('v.user_id,
                COUNT(*) AS ventas,
                COALESCE(SUM(v.total), 0) AS monto,
                COALESCE(SUM(v.total - v.costo_total), 0) AS ganancia,
                COALESCE(SUM(c.monto), 0) AS comision,
                COALESCE(SUM(CASE WHEN c.ganada_at IS NULL THEN c.monto END), 0) AS pendiente,
                COALESCE(SUM(CASE WHEN c.ganada_at IS NOT NULL AND c.liquidacion_id IS NULL THEN c.monto END), 0) AS por_pagar,
                COALESCE(SUM(CASE WHEN c.liquidacion_id IS NOT NULL THEN c.monto END), 0) AS pagada')
            ->orderByDesc('monto')
            ->get();

        $nombres = User::whereKey($filas->pluck('user_id')->filter())->pluck('name', 'id');

        return $filas->map(fn($f) => (object) [
            'clave' => $f->user_id ? (string) $f->user_id : 'sin',
            'user_id' => $f->user_id,
            'nombre' => $f->user_id ? ($nombres[$f->user_id] ?? '—') : 'Sin vendedor',
            'ventas' => (int) $f->ventas,
            'monto' => (float) $f->monto,
            'ganancia' => (float) $f->ganancia,
            'margen' => (float) $f->monto > 0 ? round((float) $f->ganancia / (float) $f->monto * 100, 1) : 0.0,
            'ticket' => (int) $f->ventas > 0 ? round((float) $f->monto / (int) $f->ventas, 2) : 0.0,
            'comision' => (float) $f->comision,
            'pendiente' => (float) $f->pendiente,
            'por_pagar' => (float) $f->por_pagar,
            'pagada' => (float) $f->pagada,
        ]);
    }

    private function detalle()
    {
        if ($this->vendedor === '') {
            return null;
        }

        return $this->ventas()
            ->leftJoin('clientes as cl', 'cl.id', '=', 'v.cliente_id')
            ->when($this->vendedor === 'sin',
                fn($q) => $q->whereNull('v.user_id'),
                fn($q) => $q->where('v.user_id', (int) $this->vendedor))
            ->orderByDesc('v.created_at')
            ->orderByDesc('v.id')
            ->select('v.id', 'v.created_at', 'v.total', 'v.costo_total', 'v.saldo', 'v.cliente',
                'cl.nombre as cliente_nombre', 'c.monto as comision', 'c.ganada_at', 'c.liquidacion_id')
            ->paginate(15, ['*'], 'ventas');
    }

    public function render()
    {
        $filas = $this->porVendedor();

        return view('livewire.reporte.reporte-vendedores-index', [
            'filas' => $filas,
            'total' => (object) [
                'ventas' => $filas->sum('ventas'),
                'monto' => $filas->sum('monto'),
                'ganancia' => $filas->sum('ganancia'),
                'comision' => $filas->sum('comision'),
                'pendiente' => $filas->sum('pendiente'),
                'por_pagar' => $filas->sum('por_pagar'),
                'pagada' => $filas->sum('pagada'),
            ],
            'detalle' => $this->detalle(),
            'nombreVendedor' => $filas->firstWhere('clave', $this->vendedor)?->nombre,
        ]);
    }
}
