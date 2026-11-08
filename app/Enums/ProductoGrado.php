<?php

namespace App\Enums;

use Illuminate\Support\Collection;

/**
 * La condicion del equipo. Antes era texto libre y habia equipos con grado
 * "1", "B", "A", "10" y "AB": imposible filtrar. Lista cerrada, la que pidio
 * la tienda (reemplazo a A+ / Grado 1 / 2 / 3). El orden de los casos es el
 * de los selects y del reporte de inventario.
 */
enum ProductoGrado: string
{
    case Nuevo = 'Nuevo';
    case SemiNuevo = 'Semi Nuevo';
    case OpenBox = 'Open Box';

    public function label(): string
    {
        return $this->value;
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
