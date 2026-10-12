<?php

namespace App\Enums;

use Illuminate\Support\Collection;

/**
 * Tipo de una reparacion.
 *
 * Antes solo existia un discriminador indirecto: `venta_id` no nulo queria
 * decir "garantia". Eso no daba sitio a un tercer caso, y obligaba a mirar una
 * clave foranea para saber de que clase de trabajo se trataba.
 *
 *  - Normal:   reparacion sobre un producto nuestro (ingreso de lote, arreglo
 *              antes de venderlo). Su costo SI entra en el costo del producto.
 *  - Garantia: reparacion de un producto ya vendido, dentro de garantia. La
 *              asumimos nosotros, asi que tambien entra en el costo.
 *  - Externo:  trabajo de pago sobre un producto que ya vendimos y cuya
 *              garantia vencio. Lo paga el cliente, de modo que NO es costo de
 *              inventario: ver Producto::recalcularCosto().
 */
enum ReparacionTipo: string
{
    case Normal = 'Normal';
    case Garantia = 'Garantia';
    case Externo = 'Externo';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Garantia => 'Garantía',
            self::Externo => 'Trabajo Externo',
        };
    }

    /** Clases de Tailwind del distintivo. Literales: no hay safelist. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Normal => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
            self::Garantia => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            self::Externo => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn($caso) => $caso->value, self::cases());
    }

    public static function toSelectArray(): Collection
    {
        return collect(self::cases())->mapWithKeys(fn($caso) => [$caso->value => $caso->label()]);
    }
}
