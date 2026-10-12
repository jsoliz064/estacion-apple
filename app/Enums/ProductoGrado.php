<?php

namespace App\Enums;

use Illuminate\Support\Collection;

/**
 * La condicion estetica del equipo. Antes era texto libre y habia equipos con
 * grado "1", "B", "A", "10" y "AB": imposible filtrar. Lista cerrada (docs/02).
 */
enum ProductoGrado: string
{
    case APlus = 'A+';
    case Uno = '1';
    case Dos = '2';
    case Tres = '3';

    public function label(): string
    {
        return match ($this) {
            self::APlus => 'A+ (como nuevo)',
            self::Uno => 'Grado 1',
            self::Dos => 'Grado 2',
            self::Tres => 'Grado 3',
        };
    }

    public static function labelDe(?string $grado): string
    {
        return self::tryFrom((string) $grado)?->label() ?? '—';
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function toSelectArray(): Collection
    {
        return collect(self::cases())->mapWithKeys(fn($c) => [$c->value => $c->label()]);
    }
}
