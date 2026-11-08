<?php

namespace App\Enums;

/**
 * La base es USD: costos, precios, ventas, compras, pagos y reportes se
 * guardan en dolares. Un monto que entro en bolivianos (la moneda alterna)
 * guarda los Bs y el tipo de cambio, y su monto en USD (Bs / TC).
 */
enum Moneda: string
{
    case BOB = 'BOB';
    case USD = 'USD';

    public static function base(): self
    {
        return self::USD;
    }

    public static function alterna(): self
    {
        return self::BOB;
    }

    public function esBase(): bool
    {
        return $this === self::base();
    }

    public function simbolo(): string
    {
        return match ($this) {
            self::BOB => 'Bs',
            self::USD => 'USD',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
