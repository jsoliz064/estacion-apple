<?php

namespace App\Traits;

use Illuminate\Support\Carbon;

/**
 * El periodo de los reportes nuevos: el mes actual por defecto, con atajos de
 * mes anterior y siguiente. periodo() siempre devuelve un rango valido, aunque
 * el navegador mande fechas invertidas o vacias.
 */
trait ReportePeriodoTrait
{
    public string $desde = '';
    public string $hasta = '';

    protected function iniciarPeriodo(): void
    {
        $this->desde = now()->startOfMonth()->toDateString();
        $this->hasta = now()->endOfMonth()->toDateString();
    }

    /** Mueve el periodo un mes (0 = el actual). */
    public function mes(int $delta): void
    {
        $base = $delta === 0 ? now() : $this->fecha($this->desde, now())->addMonthsNoOverflow($delta);
        $this->desde = $base->copy()->startOfMonth()->toDateString();
        $this->hasta = $base->copy()->endOfMonth()->toDateString();
    }

    /** @return array{0: Carbon, 1: Carbon} [inicio del primer dia, fin del ultimo] */
    protected function periodo(): array
    {
        $desde = $this->fecha($this->desde, now()->startOfMonth())->startOfDay();
        $hasta = $this->fecha($this->hasta, now())->endOfDay();

        return $desde->lte($hasta)
            ? [$desde, $hasta]
            : [$hasta->copy()->startOfDay(), $desde->copy()->endOfDay()];
    }

    public function periodoTexto(): string
    {
        [$desde, $hasta] = $this->periodo();

        return 'del ' . $desde->format('d/m/Y') . ' al ' . $hasta->format('d/m/Y');
    }

    private function fecha(?string $valor, Carbon $defecto): Carbon
    {
        if (!$valor) {
            return $defecto->copy();
        }

        return rescue(fn() => Carbon::parse($valor), $defecto->copy(), false);
    }
}
