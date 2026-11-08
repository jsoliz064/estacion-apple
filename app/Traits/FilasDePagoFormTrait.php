<?php

namespace App\Traits;

use App\Enums\Moneda;
use App\Models\TipoCambio;

/**
 * Las filas de pago de una pantalla (venta: lo cobrado al vender; compra: lo
 * pagado al recibir): metodo, USD (la base) o Bs con su tipo de cambio. La vista es el
 * parcial livewire.partials.filas-pago. Del lado del servidor las valida y
 * junta Services\Concerns\FilasDePago.
 *
 * Mientras nadie toque los montos, el unico pago (en USD) sigue a lo que falta:
 * la operacion de contado en efectivo no pide escribir nada. Cada componente
 * decide a que sigue en seguirTotal().
 */
trait FilasDePagoFormTrait
{
    /** [['metodo_pago_id', 'moneda', 'monto' (USD), 'monto_moneda' (Bs), 'tipo_cambio'], ...] */
    public array $pagos = [];

    public bool $montoTocado = false;

    /** Lo que falta pagar segun la pantalla: la fila nueva lo propone. */
    abstract public function saldoPrevisto(): float;

    protected function filaPago($metodoId = null, float $monto = 0): array
    {
        return [
            'metodo_pago_id' => $metodoId,
            'moneda' => Moneda::base()->value,
            'monto' => $monto,
            'monto_moneda' => '',
            'tipo_cambio' => TipoCambio::actual(),
        ];
    }

    public function agregarPago(): void
    {
        $this->montoTocado = true;
        $this->pagos[] = $this->filaPago(null, max(0, $this->saldoPrevisto()));
    }

    public function quitarPago(int $index): void
    {
        unset($this->pagos[$index]);
        $this->pagos = array_values($this->pagos);
        $this->montoTocado = true;
    }

    public function updatedPagos($valor, $clave): void
    {
        if (!preg_match('/^(\d+)\.(monto|monto_moneda|moneda|tipo_cambio)$/', (string) $clave, $m)) {
            return;
        }

        $this->montoTocado = true;
        $i = (int) $m[1];
        $fila = $this->pagos[$i] ?? null;

        if (!$fila || ($fila['moneda'] ?? '') !== Moneda::alterna()->value) {
            return;
        }

        // Pasar la fila a Bs propone en Bs lo que ya decia en USD (lo que el
        // cliente debe, al TC de la fila).
        if ($m[2] === 'moneda' && !(float) ($fila['monto_moneda'] ?: 0)) {
            $this->pagos[$i]['monto_moneda'] = round((float) ($fila['monto'] ?: 0) * (float) ($fila['tipo_cambio'] ?: 0), 2);
        }

        // `monto` sigue siendo el USD de la fila: es lo que suma y lo que valida
        // el servidor contra Bs / TC.
        $this->pagos[$i]['monto'] = $this->montoBaseDe($this->pagos[$i]);
    }

    /** Lo que vale una fila en USD (en Bs, bolivianos / tasa). */
    public function montoBaseDe(array $pago): float
    {
        if (($pago['moneda'] ?? '') === Moneda::alterna()->value) {
            $tc = (float) ($pago['tipo_cambio'] ?: 0);

            return $tc > 0 ? round((float) ($pago['monto_moneda'] ?: 0) / $tc, 2) : 0.0;
        }

        return round((float) ($pago['monto'] ?: 0), 2);
    }

    public function sumaFilas(): float
    {
        return round(array_sum(array_map(fn($p) => $this->montoBaseDe($p), $this->pagos)), 2);
    }

    /** El unico pago en USD, sin tocar, pasa a valer $monto. */
    protected function seguirMonto(float $monto): void
    {
        if (!$this->montoTocado && count($this->pagos) === 1 && ($this->pagos[0]['moneda'] ?? '') === Moneda::base()->value) {
            $this->pagos[0]['monto'] = max(0, round($monto, 2));
        }
    }
}
