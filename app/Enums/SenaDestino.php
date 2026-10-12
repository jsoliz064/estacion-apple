<?php

namespace App\Enums;

/** Que paso con la seña de una reserva cancelada (decision del usuario: se elige al cancelar). */
enum SenaDestino: string
{
    case Devuelta = 'Devuelta';
    case Retenida = 'Retenida';

    public function label(): string
    {
        return match ($this) {
            self::Devuelta => 'Seña devuelta al cliente',
            self::Retenida => 'Seña retenida por el negocio',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
