<?php

namespace App\Support;

use App\Models\TipoCambio;

/**
 * Como se escribe el dinero, en un solo lugar. Todo se guarda en USD; los Bs
 * son una vista: USD x el tipo de cambio (el del dia, o el que congelo el
 * documento). Antes cada vista escribia "Bs {{ number_format(...) }}" a mano.
 */
class Dinero
{
    public static function usd(float|int|string|null $monto): string
    {
        return 'USD ' . number_format((float) $monto, 2);
    }

    /** El equivalente en Bs. Sin $tc, al tipo de cambio del dia. */
    public static function bs(float|int|string|null $usd, float|int|string|null $tc = null): string
    {
        return 'Bs ' . number_format(self::aBs($usd, $tc), 2);
    }

    /** Un monto que ya esta en Bs (lo que se cargo o se pago en bolivianos). */
    public static function soloBs(float|int|string|null $bs): string
    {
        return 'Bs ' . number_format((float) $bs, 2);
    }

    public static function aBs(float|int|string|null $usd, float|int|string|null $tc = null): float
    {
        return round((float) $usd * (float) ($tc ?: TipoCambio::actual()), 2);
    }

    /** «USD 780.00 (Bs 5,428.80)». */
    public static function ambos(float|int|string|null $usd, float|int|string|null $tc = null): string
    {
        return self::usd($usd) . ' (' . self::bs($usd, $tc) . ')';
    }

    /** El tipo de cambio con los decimales que tenga, minimo dos: 6.96, 6.965. */
    public static function tc(float|int|string|null $tc): string
    {
        [$entero, $decimales] = explode('.', number_format((float) $tc, 4, '.', ''));

        return $entero . '.' . str_pad(rtrim($decimales, '0'), 2, '0');
    }
}
