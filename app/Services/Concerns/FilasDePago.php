<?php

namespace App\Services\Concerns;

use App\Enums\Moneda;
use App\Support\Dinero;
use Illuminate\Validation\ValidationException;

/**
 * Las filas de pago, iguales para los cobros de ventas (PagoService) y los
 * pagos a proveedores (PagoProveedorService): en USD (la base) o en Bs con su
 * tipo de cambio, juntando las del mismo metodo y moneda. Un solo sitio, para que dos
 * copias no diverjan (CLAUDE.md: "la copia que divergio").
 */
trait FilasDePago
{
    protected function frase(array $pago, string $metodo): string
    {
        $texto = Dinero::usd($pago['monto']) . ' en ' . $metodo;

        if (($pago['moneda'] ?? Moneda::base()->value) === Moneda::alterna()->value) {
            $texto .= ' (' . Dinero::soloBs($pago['monto_moneda']) . ' a ' . Dinero::tc($pago['tipo_cambio']) . ')';
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
            $moneda = Moneda::tryFrom((string) ($pago['moneda'] ?? '')) ?? Moneda::base();
            $alterna = !$moneda->esBase();
            $bs = round((float) ($pago['monto_moneda'] ?? 0), 2);
            $tc = round((float) ($pago['tipo_cambio'] ?? 0), 4);
            $monto = round((float) ($pago['monto'] ?? 0), 2);

            // En Bs manda bolivianos / tasa. Se respeta el monto en USD que
            // llega solo si difiere por redondeo: pagar en Bs el saldo exacto
            // (saldo x TC) da, al dividir, un centavo de mas o de menos, y la
            // pantalla manda el saldo tal cual. Lo mismo un cobro en Bs
            // repartido entre varias ventas.
            if ($alterna && $tc > 0 && abs($monto - round($bs / $tc, 2)) > 0.01) {
                $monto = round($bs / $tc, 2);
            }

            // Fila en 0 (vacia, o con el metodo puesto pero sin monto, como la
            // que propone una compra que trae solo equipos): no es un pago.
            $sinMonto = $alterna ? $bs == 0.0 : $monto == 0.0;
            if ($sinMonto) {
                continue;
            }

            if ($alterna && ($bs <= 0 || $tc <= 0)) {
                throw ValidationException::withMessages(['pagos' => 'Un pago en bolivianos necesita el monto en Bs y el tipo de cambio.']);
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
                // Mismo metodo y moneda: se suman (en Bs, con la tasa de la
                // primera fila; dos tasas distintas en una venta serian raras).
                if ($alterna) {
                    $limpios[$clave]['monto_moneda'] = round($limpios[$clave]['monto_moneda'] + $bs, 2);
                    $limpios[$clave]['monto'] = round($limpios[$clave]['monto'] + $monto, 2);
                } else {
                    $limpios[$clave]['monto'] = round($limpios[$clave]['monto'] + $monto, 2);
                }
                continue;
            }

            $limpios[$clave] = [
                'metodo_pago_id' => $metodo,
                'monto' => $monto,
                'moneda' => $moneda->value,
                'monto_moneda' => $alterna ? $bs : null,
                'tipo_cambio' => $alterna ? $tc : null,
                'nota' => $nota === '' ? null : mb_substr($nota, 0, 255),
            ];
        }

        return array_values($limpios);
    }
}
