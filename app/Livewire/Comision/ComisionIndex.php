<?php

namespace App\Livewire\Comision;

use App\Models\Comision;
use App\Models\ComisionLiquidacion;
use App\Models\Tecnicos;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Comisiones (docs/05): lo de todos, por persona y por periodo, y las
 * liquidaciones. El periodo (un mes por defecto) es el del reporte: lo ganado y
 * lo pagado en esas fechas. Pendiente y por pagar son a hoy.
 */
class ComisionIndex extends Component
{
    public string $desde = '';
    public string $hasta = '';

    public function mount(): void
    {
        $this->desde = now()->startOfMonth()->toDateString();
        $this->hasta = now()->endOfMonth()->toDateString();
    }

    #[On('comisionesActualizadas')]
    public function refrescar(): void
    {
    }

    /** Mueve el periodo un mes (o vuelve al actual). */
    public function mes(int $delta): void
    {
        $base = $delta === 0 ? now() : $this->desdeCarbon()->addMonthsNoOverflow($delta);
        $this->desde = $base->copy()->startOfMonth()->toDateString();
        $this->hasta = $base->copy()->endOfMonth()->toDateString();
    }

    public function liquidar(string $clave): void
    {
        abort_unless(auth()->user()->can('comision.liquidar'), 403);

        [$desde, $hasta] = $this->periodo();
        $this->dispatch('openComisionLiquidarModal', $clave, $desde, $hasta);
    }

    public function verDetalle(string $clave): void
    {
        $this->dispatch('comisionPersona', $clave);
    }

    public function verLiquidacion(int $id): void
    {
        $this->dispatch('openLiquidacionVerModal', $id);
    }

    /** @return array{0: string, 1: string} el periodo valido (desde <= hasta) */
    private function periodo(): array
    {
        $desde = $this->desdeCarbon();
        $hasta = rescue(fn() => Carbon::parse($this->hasta), now(), false);

        return $desde->lte($hasta)
            ? [$desde->toDateString(), $hasta->toDateString()]
            : [$hasta->toDateString(), $desde->toDateString()];
    }

    private function desdeCarbon(): Carbon
    {
        return rescue(fn() => Carbon::parse($this->desde), now()->startOfMonth(), false);
    }

    public function render()
    {
        [$desde, $hasta] = $this->periodo();
        $finHasta = Carbon::parse($hasta)->endOfDay();

        // Por persona: el beneficiario es un vendedor o un tecnico.
        $filas = DB::table('comisiones as c')
            ->leftJoin('comisiones_liquidaciones as l', 'l.id', '=', 'c.liquidacion_id')
            ->groupBy('c.user_id', 'c.tecnico_id')
            ->selectRaw('c.user_id, c.tecnico_id,
                SUM(CASE WHEN c.ganada_at IS NULL THEN c.monto ELSE 0 END) AS pendiente,
                SUM(CASE WHEN c.ganada_at IS NOT NULL AND c.liquidacion_id IS NULL THEN c.monto ELSE 0 END) AS por_pagar,
                SUM(CASE WHEN c.ganada_at BETWEEN ? AND ? THEN c.monto ELSE 0 END) AS ganado,
                SUM(CASE WHEN l.created_at BETWEEN ? AND ? THEN c.monto ELSE 0 END) AS pagado', [$desde, $finHasta, $desde, $finHasta])
            ->havingRaw('pendiente > 0 OR por_pagar > 0 OR ganado > 0 OR pagado > 0')
            ->get();

        $usuarios = User::whereKey($filas->pluck('user_id')->filter())->pluck('name', 'id');
        $tecnicos = Tecnicos::whereKey($filas->pluck('tecnico_id')->filter())->pluck('nombre', 'id');

        $porPersona = $filas->map(fn($f) => (object) [
            'clave' => $f->user_id ? 'U-' . $f->user_id : 'T-' . $f->tecnico_id,
            'nombre' => $f->user_id ? ($usuarios[$f->user_id] ?? '—') : ($tecnicos[$f->tecnico_id] ?? '—'),
            'rol' => $f->user_id ? 'Vendedor' : 'Técnico',
            'pendiente' => (float) $f->pendiente,
            'por_pagar' => (float) $f->por_pagar,
            'ganado' => (float) $f->ganado,
            'pagado' => (float) $f->pagado,
        ])->sortByDesc('por_pagar')->values();

        $liquidaciones = ComisionLiquidacion::with(['user:id,name', 'tecnico:id,nombre', 'pagadoPor:id,name'])
            ->whereBetween('created_at', [$desde, $finHasta])
            ->latest('id')
            ->get();

        return view('livewire.comision.comision-index', [
            'cifras' => Comision::cifras(Comision::query()),
            'ganadoPeriodo' => (float) $porPersona->sum('ganado'),
            'pagadoPeriodo' => (float) $liquidaciones->sum('total'),
            'porPersona' => $porPersona,
            'liquidaciones' => $liquidaciones,
            'periodoTexto' => Carbon::parse($desde)->format('d/m/Y') . ' al ' . Carbon::parse($hasta)->format('d/m/Y'),
        ]);
    }
}
