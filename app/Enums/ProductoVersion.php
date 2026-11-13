<?php

namespace App\Enums;

use Illuminate\Support\Collection;

/**
 * Como se conecta el equipo. «Solo Wi-Fi» (iPad, Mac, reloj sin celular) no
 * tiene chip ni eSIM, y casi siempre tampoco IMEI: ese equipo se identifica por
 * su serial (ver productos_imei_o_serial).
 */
enum ProductoVersion: string
{
    case Chip = 'Chip';
    case eSIM = 'eSIM';
    case Wifi = 'Wifi';

    public function label(): string
    {
        return match ($this) {
            self::Wifi => 'Solo Wi‑Fi',
            default => $this->value,
        };
    }

    public static function labelDe(?string $version): string
    {
        return self::tryFrom((string) $version)?->label() ?? '—';
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
