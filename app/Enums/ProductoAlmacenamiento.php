<?php

namespace App\Enums;

enum ProductoAlmacenamiento: string
{
    case GB32 = '32GB';
    case GB64 = '64GB';
    case GB128 = '128GB';
    case GB256 = '256GB';
    case GB512 = '512GB';
    case TB1 = '1TB';
    case TB2 = '2TB';

     public function label(): string
    {
        return match ($this) {
            self::GB32 => '32GB',
            self::GB64 => '64GB',
            self::GB128 => '128GB',
            self::GB256 => '256GB',
            self::GB512 => '512GB',
            self::TB1 => '1TB',
            self::TB2 => '2TB',
        };
    }
}
