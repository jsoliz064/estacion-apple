<?php

namespace App\Enums;

/** De donde sale una comision: la venta (vendedor) o la reparacion (tecnico). */
enum ComisionOrigen: string
{
    case Venta = 'Venta';
    case Reparacion = 'Reparacion';

    public function label(): string
    {
        return match ($this) {
            self::Venta => 'Venta',
            self::Reparacion => 'Reparación',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
