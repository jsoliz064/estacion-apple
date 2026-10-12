<?php

namespace App\Traits;

use App\Enums\Moneda;
use App\Services\PagoService;

/**
 * Las filas de pago de una pantalla (venta: lo cobrado al vender; compra: lo
 * pagado al recibir): metodo, Bs o USD con su tipo de cambio. La vista es el
 * parcial livewire.partials.filas-pago. Del lado del servidor las valida y
 * junta Services\Concerns\FilasDePago.
 *
 * Mientras nadie toque los montos, el unico pago (en Bs) sigue a lo que falta:
 * la operacion de contado en efectivo no pide escribir nada. Cada componente
 * decide a que sigue en seguirTotal().
 */
trait FilasDePagoFormTrait
{
    /** [['metodo_pago_id', 'moneda', 'monto' (Bs), 'monto_moneda' (USD), 'tipo_cambio'], ...] */
    public array $pagos = [];

    public bool $montoTocado = false;

    /** Lo que falta pagar segun la pantalla: la fila nueva lo propone. */
    abstract public function saldoPrevisto(): float;

    protected function filaPago($metodoId = null, float $monto = 0): array
    {
        return [
            'metodo_pago_id' => $metodoId,
            'moneda' => Moneda::BOB->value,
            'monto' => $monto,
            'monto_moneda' => '',
            'tipo_cambio' => PagoService::ultimoTipoCambio(),
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
        if (preg_match('/\.(monto|monto_moneda|moneda)$/', (string) $clave)) {
            $this->montoTocado = true;
        }
    }

    /** El equivalente en Bs de una fila (en USD, dolares x tasa). */
    public function montoBsDe(array $pago): float
    {
        if (($pago['moneda'] ?? 'BOB') === Moneda::USD->value) {
            return round((float) ($pago['monto_moneda'] ?: 0) * (float) ($pago['tipo_cambio'] ?: 0), 2);
        }

        return round((float) ($pago['monto'] ?: 0), 2);
    }

    public function sumaFilas(): float
    {
        return round(array_sum(array_map(fn($p) => $this->montoBsDe($p), $this->pagos)), 2);
    }

    /** El unico pago en Bs, sin tocar, pasa a valer $monto. */
    protected function seguirMonto(float $monto): void
    {
        if (!$this->montoTocado && count($this->pagos) === 1 && ($this->pagos[0]['moneda'] ?? 'BOB') === Moneda::BOB->value) {
            $this->pagos[0]['monto'] = max(0, round($monto, 2));
        }
    }
}
