<?php

namespace App\Enums;

use Illuminate\Support\Collection;

/**
 * Tipos de movimiento del historial de un articulo (repuesto o accesorio).
 *
 * OJO: los `value` de este enum se escriben LITERALMENTE dentro del UNION de
 * MovimientoStock::paraArticulo() ("'Compra' as tipo"). Si cambias un value aqui
 * y no alli, el filtro deja de encontrar filas silenciosamente. Y al reves: la
 * tabla filtra con array_intersect(..., ::values()) como whitelist, asi que una
 * rama nueva que no tenga su case aqui se filtra fuera sin aviso.
 */
enum MovimientoStockTipo: string
{
    case Compra = 'Compra';
    case Venta = 'Venta';
    case Reparacion = 'Reparacion';
    case Regalo = 'Regalo';
    case Baja = 'Baja';
    case Transferencia = 'Transferencia';

    public function label(): string
    {
        return match ($this) {
            self::Compra => 'Compra',
            self::Venta => 'Venta',
            self::Reparacion => 'Reparación',
            self::Regalo => 'Regalo',
            self::Baja => 'Baja',
            self::Transferencia => 'Transferencia',
        };
    }

    /**
     * Espeja la columna `direccion` del UNION. Null para Transferencia, que el
     * UNION parte en DOS filas opuestas (sale de una sucursal y entra en otra).
     */
    public function direccion(): ?string
    {
        return match ($this) {
            self::Compra => 'Entrada',
            self::Venta, self::Reparacion, self::Regalo, self::Baja => 'Salida',
            self::Transferencia => null,
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Compra => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            self::Venta => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            self::Reparacion => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
            self::Regalo => 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200',
            self::Baja => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            self::Transferencia => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return Collection<string,string> value => label (opciones del filtro) */
    public static function toSelectArray(): Collection
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [$case->value => $case->label()]);
    }
}
