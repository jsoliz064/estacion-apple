<?php

namespace App\Enums;

enum ReclamoEstado: string
{
    case Abierto = 'Abierto';
    case Resuelto = 'Resuelto';

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Abierto => 'bg-rose-100 text-rose-800',
            self::Resuelto => 'bg-green-100 text-green-800',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
