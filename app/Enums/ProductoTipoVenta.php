<?php

namespace App\Enums;

use Illuminate\Support\Collection;

/**
 * Como se ofrece el equipo. No cambia el calculo de ganancia ni de comision:
 * "Venta externa" marca lo que se vende fuera del local (redes, a domicilio,
 * otro punto) para separarlo en los reportes (docs/02).
 *
 * Antes la oferta era un ESTADO del producto; ahora un equipo en oferta esta
 * en Inventario con tipo_venta = Oferta. La linea de venta congela este valor
 * (ventas_detalles.tipo_venta) para que los reportes no cambien si despues se
 * edita el equipo.
 */
enum ProductoTipoVenta: string
{
    case Venta = 'Venta';
    case Oferta = 'Oferta';
    case VentaExterna = 'Venta externa';

    public function label(): string
    {
        return $this->value;
    }

    /** Clases LITERALES en cada rama: Tailwind no tiene safelist. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Venta => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
            self::Oferta => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
            self::VentaExterna => 'bg-desert-100 text-desert-800 dark:bg-desert-900 dark:text-desert-100',
        };
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
