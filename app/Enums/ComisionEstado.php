<?php

namespace App\Enums;

/**
 * El estado de una comision (docs/05). DERIVADO, no guardado: sale de
 * `ganada_at` y `liquidacion_id`, asi que no puede contradecirlos.
 *  - Pendiente: la venta no esta cobrada entera, o la reparacion no termino.
 *  - Por pagar: ganada, esperando liquidacion.
 *  - Pagada:    ya liquidada.
 */
enum ComisionEstado: string
{
    case Pendiente = 'Pendiente';
    case PorPagar = 'Por pagar';
    case Pagada = 'Pagada';

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pendiente => 'bg-amber-100 text-amber-800',
            self::PorPagar => 'bg-blue-100 text-blue-800',
            self::Pagada => 'bg-green-100 text-green-800',
        };
    }

    public function badge(): string
    {
        return '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold ' . $this->badgeClasses() . '">' . e($this->value) . '</span>';
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
