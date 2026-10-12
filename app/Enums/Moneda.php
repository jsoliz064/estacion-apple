<?php

namespace App\Enums;

/**
 * La moneda en que se entrego un pago. El inventario y la venta estan en Bs;
 * un pago en USD guarda los dolares y el tipo de cambio, y su `monto` en Bs.
 */
enum Moneda: string
{
    case BOB = 'BOB';
    case USD = 'USD';

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
