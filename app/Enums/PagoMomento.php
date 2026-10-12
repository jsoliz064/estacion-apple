<?php

namespace App\Enums;

/**
 * Cuando entro un pago: al registrar la venta, o despues, en un cobro de una
 * venta a credito. Lo distingue la ficha del cliente y, en la etapa 7, la
 * comision (se gana con el ultimo pago).
 */
enum PagoMomento: string
{
    case Venta = 'Venta';
    case Cobro = 'Cobro';
    // La seña de una reserva, que entra como pago de la venta al concretarla.
    case Sena = 'Sena';

    public function label(): string
    {
        return match ($this) {
            self::Venta => 'Al vender',
            self::Cobro => 'Cobro',
            self::Sena => 'Seña de reserva',
        };
    }

    public static function labelDe(?string $momento): string
    {
        return self::tryFrom((string) $momento)?->label() ?? (string) $momento;
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
