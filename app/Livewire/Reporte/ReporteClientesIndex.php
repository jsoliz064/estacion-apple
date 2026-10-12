<?php

namespace App\Livewire\Reporte;

use App\Traits\ReporteFiltrosTrait;
use App\Traits\ReportePeriodoTrait;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Reporte por cliente (docs/09).
 *
 *  - Los que mas compran en el periodo (por monto o por cantidad).
 *  - La deuda por cliente A HOY, con su antiguedad en tramos (por la fecha de
 *    cada venta con saldo).
 *  - Los clientes nuevos del periodo (alta de la ficha).
 *  - Las ventas de mostrador: sin ficha de cliente.
 *
 * Va estrictamente por ventas.cliente_id, como la ficha del cliente: la columna
 * de texto `ventas.cliente` es archivo. El filtro de sucursal mira la sucursal
 * de la venta (un cliente no es de una sucursal).
 */
class ReporteClientesIndex extends Component
{
    use ReporteFiltrosTrait;
    use ReportePeriodoTrait {
        mes as traitMes;
    }
    use WithPagination;

    public const TOP = 20;

    /** monto | compras */
    public string $ordenTop = 'monto';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('reporte.clientes'), 403);
        $this->iniciarPeriodo();
    }

    public function updated($propiedad): void
    {
        if ($propiedad === 'sucursalId') {
            $this->resetPage('deudores');
        }
    }

    public function mes(int $delta): void
    {
        $this->traitMes($delta);
    }

    private function ventasDelPeriodo()
    {
        [$desde, $hasta] = $this->periodo();

        return $this->porSucursal(DB::table('ventas as v')->whereBetween('v.created_at', [$desde, $hasta]), 'v.sucursal_id');
    }

    private function top()
    {
        $orden = $this->ordenTop === 'compras' ? 'compras' : 'monto';

        return $this->ventasDelPeriodo()
            ->join('clientes as cl', 'cl.id', '=', 'v.cliente_id')
            ->groupBy('cl.id', 'cl.nombre')
            ->selectRaw('cl.id, cl.nombre, COUNT(*) AS compras, SUM(v.total) AS monto, MAX(v.created_at) AS ultima')
            ->orderByDesc($orden)
            ->orderByDesc($orden === 'monto' ? 'compras' : 'monto')
            ->limit(self::TOP)
            ->get();
    }

    /** La deuda a hoy, por cliente, con sus tramos de antiguedad. */
    private function deuda()
    {
        $base = $this->porSucursal(DB::table('ventas as v')
            ->join('clientes as cl', 'cl.id', '=', 'v.cliente_id')
            ->where('v.saldo', '>', 0), 'v.sucursal_id');

        $dias = 'DATEDIFF(CURDATE(), DATE(v.created_at))';

        $totales = (clone $base)->selectRaw("COUNT(DISTINCT cl.id) AS clientes, COALESCE(SUM(v.saldo), 0) AS deuda,
            COALESCE(SUM(CASE WHEN {$dias} <= 30 THEN v.saldo END), 0) AS t30,
            COALESCE(SUM(CASE WHEN {$dias} BETWEEN 31 AND 60 THEN v.saldo END), 0) AS t60,
            COALESCE(SUM(CASE WHEN {$dias} BETWEEN 61 AND 90 THEN v.saldo END), 0) AS t90,
            COALESCE(SUM(CASE WHEN {$dias} > 90 THEN v.saldo END), 0) AS tmas")->first();

        $lista = $base
            ->groupBy('cl.id', 'cl.nombre', 'cl.telefono')
            ->selectRaw("cl.id, cl.nombre, cl.telefono, COUNT(*) AS ventas, SUM(v.saldo) AS deuda,
                MAX({$dias}) AS antiguedad,
                COALESCE(SUM(CASE WHEN {$dias} <= 30 THEN v.saldo END), 0) AS t30,
                COALESCE(SUM(CASE WHEN {$dias} BETWEEN 31 AND 60 THEN v.saldo END), 0) AS t60,
                COALESCE(SUM(CASE WHEN {$dias} BETWEEN 61 AND 90 THEN v.saldo END), 0) AS t90,
                COALESCE(SUM(CASE WHEN {$dias} > 90 THEN v.saldo END), 0) AS tmas")
            ->orderByDesc('deuda')
            ->paginate(20, ['*'], 'deudores');

        return [$lista, $totales];
    }

    private function nuevos()
    {
        [$desde, $hasta] = $this->periodo();

        // Sus compras del periodo, en la sucursal elegida.
        $compras = $this->ventasDelPeriodo()
            ->whereNotNull('v.cliente_id')
            ->groupBy('v.cliente_id')
            ->selectRaw('v.cliente_id, COUNT(*) AS compras, SUM(v.total) AS monto');

        return DB::table('clientes as cl')
            ->leftJoinSub($compras, 'cp', 'cp.cliente_id', '=', 'cl.id')
            ->whereBetween('cl.created_at', [$desde, $hasta])
            ->orderByDesc('cl.created_at')
            ->get(['cl.id', 'cl.nombre', 'cl.telefono', 'cl.created_at', DB::raw('COALESCE(cp.compras, 0) AS compras'), DB::raw('COALESCE(cp.monto, 0) AS monto')]);
    }

    private function mostrador(): object
    {
        $fila = $this->ventasDelPeriodo()
            ->selectRaw('COUNT(*) AS ventas, COALESCE(SUM(v.total), 0) AS monto,
                SUM(CASE WHEN v.cliente_id IS NULL THEN 1 ELSE 0 END) AS sin_ficha,
                COALESCE(SUM(CASE WHEN v.cliente_id IS NULL THEN v.total END), 0) AS monto_sin_ficha')
            ->first();

        return (object) [
            'ventas' => (int) $fila->ventas,
            'monto' => (float) $fila->monto,
            'sin_ficha' => (int) $fila->sin_ficha,
            'monto_sin_ficha' => (float) $fila->monto_sin_ficha,
        ];
    }

    public function render()
    {
        [$deudores, $deuda] = $this->deuda();

        return view('livewire.reporte.reporte-clientes-index', [
            'top' => $this->top(),
            'deudores' => $deudores,
            'deuda' => $deuda,
            'nuevos' => $this->nuevos(),
            'mostrador' => $this->mostrador(),
        ]);
    }
}
