<?php

namespace App\Enums;

use Illuminate\Support\Collection;

/**
 * Lo que vende o compra una linea de ventas_detalles / compras_detalles.
 *
 * Respalda la columna GENERADA `tipo` de esas tablas: la calcula MySQL desde la
 * FK que no es NULL, asi que nunca puede contradecirlas. Los values tienen que
 * coincidir con los literales del CASE de esas migraciones.
 */
enum LineaTipo: string
{
    case Producto = 'Producto';
    case Repuesto = 'Repuesto';
    case Accesorio = 'Accesorio';

    public function label(): string
    {
        return match ($this) {
            self::Producto => 'Equipo',
            self::Repuesto => 'Repuesto',
            self::Accesorio => 'Accesorio',
        };
    }

    /** El articulo de stock que representa, o null para un equipo. */
    public function articulo(): ?ArticuloTipo
    {
        return match ($this) {
            self::Producto => null,
            self::Repuesto => ArticuloTipo::Repuesto,
            self::Accesorio => ArticuloTipo::Accesorio,
        };
    }

    /** La columna de FK de la linea. */
    public function columna(): string
    {
        return match ($this) {
            self::Producto => 'producto_id',
            self::Repuesto => 'repuesto_id',
            self::Accesorio => 'accesorio_id',
        };
    }

    /** Clases LITERALES en cada rama: Tailwind no tiene safelist. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Producto => 'bg-brand-100 text-brand-800 dark:bg-brand-900 dark:text-brand-100',
            self::Repuesto => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            self::Accesorio => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        };
    }

    public static function badge(?string $tipo): string
    {
        $caso = self::tryFrom((string) $tipo);

        return $caso
            ? '<span class="px-2 py-0.5 rounded-full text-xs font-semibold ' . $caso->badgeClasses() . '">' . e($caso->label()) . '</span>'
            : '';
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
