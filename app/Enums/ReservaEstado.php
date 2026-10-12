<?php

namespace App\Enums;

/** El ciclo de una reserva: se concreta en una venta o se cancela. No vence. */
enum ReservaEstado: string
{
    case Activa = 'Activa';
    case Concretada = 'Concretada';
    case Cancelada = 'Cancelada';

    public function label(): string
    {
        return $this->value;
    }

    /** Clases LITERALES: Tailwind no tiene safelist. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Activa => 'bg-amber-100 text-amber-800',
            self::Concretada => 'bg-green-100 text-green-800',
            self::Cancelada => 'bg-gray-200 text-gray-700',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
