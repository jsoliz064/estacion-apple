<?php

namespace App\Services\Concerns;

use App\Enums\Moneda;
use Illuminate\Validation\ValidationException;

/**
 * Las filas de pago, iguales para los cobros de ventas (PagoService) y los
 * pagos a proveedores (PagoProveedorService): en Bs o en USD con su tipo de
 * cambio, juntando las del mismo metodo y moneda. Un solo sitio, para que dos
 * copias no diverjan (CLAUDE.md: "la copia que divergio").
 */
trait FilasDePago
{
    protected function frase(array $pago, string $metodo): string
    {
        $texto = 'Bs ' . number_format((float) $pago['monto'], 2) . ' en ' . $metodo;

        if (($pago['moneda'] ?? 'BOB') === Moneda::USD->value) {
            $texto .= ' (USD ' . number_format((float) $pago['monto_moneda'], 2) . ' a ' . (float) $pago['tipo_cambio'] . ')';
        }

        return $texto;
    }

    /**
     * Las filas del formulario, limpias. Dos filas del mismo metodo y moneda
     * son un solo pago: lo exige tambien el indice vp_clave_idem_venta_metodo_unico.
     *
     * @return array<int, array{metodo_pago_id:int, monto:float, moneda:string, monto_moneda:?float, tipo_cambio:?float, nota:?string}>
     */
    protected function normalizar(array $pagos): array
    {
        $limpios = [];

        foreach ($pagos as $pago) {
            $moneda = Moneda::tryFrom((string) ($pago['moneda'] ?? 'BOB')) ?? Moneda::BOB;
            $usd = round((float) ($pago['monto_moneda'] ?? 0), 2);
            $tc = round((float) ($pago['tipo_cambio'] ?? 0), 4);
            $monto = round((float) ($pago['monto'] ?? 0), 2);

            // En USD manda dolares x tasa. Se respeta el monto en Bs que llega
            // solo si difiere por redondeo: un cobro en USD repartido entre
            // varias ventas convierte cada parte, y sin esto quedaba un centavo
            // por encima del saldo.
            if ($moneda === Moneda::USD && abs($monto - round($usd * $tc, 2)) > 0.05) {
                $monto = round($usd * $tc, 2);
            }

            // Fila en 0 (vacia, o con el metodo puesto pero sin monto, como la
            // que propone una compra que trae solo equipos): no es un pago.
            $sinMonto = $moneda === Moneda::USD ? $usd == 0.0 : $monto == 0.0;
            if ($sinMonto) {
                continue;
            }

            if ($moneda === Moneda::USD && ($usd <= 0 || $tc <= 0)) {
                throw ValidationException::withMessages(['pagos' => 'Un pago en dólares necesita el monto en USD y el tipo de cambio.']);
            }

            if ($monto <= 0) {
                throw ValidationException::withMessages(['pagos' => 'Cada pago tiene que ser mayor a cero.']);
            }

            if (empty($pago['metodo_pago_id'])) {
                throw ValidationException::withMessages(['pagos' => 'Elige el método de cada pago.']);
            }

            $metodo = (int) $pago['metodo_pago_id'];
            $clave = $metodo . ':' . $moneda->value;
            $nota = trim((string) ($pago['nota'] ?? ''));

            if (isset($limpios[$clave])) {
                // Mismo metodo y moneda: se suman (en USD, con la tasa de la
                // primera fila; dos tasas distintas en una venta serian raras).
                if ($moneda === Moneda::USD) {
                    $limpios[$clave]['monto_moneda'] = round($limpios[$clave]['monto_moneda'] + $usd, 2);
                    $limpios[$clave]['monto'] = round($limpios[$clave]['monto_moneda'] * $limpios[$clave]['tipo_cambio'], 2);
                } else {
                    $limpios[$clave]['monto'] = round($limpios[$clave]['monto'] + $monto, 2);
                }
                continue;
            }

            $limpios[$clave] = [
                'metodo_pago_id' => $metodo,
                'monto' => $monto,
                'moneda' => $moneda->value,
                'monto_moneda' => $moneda === Moneda::USD ? $usd : null,
                'tipo_cambio' => $moneda === Moneda::USD ? $tc : null,
                'nota' => $nota === '' ? null : mb_substr($nota, 0, 255),
            ];
        }

        return array_values($limpios);
    }
}
