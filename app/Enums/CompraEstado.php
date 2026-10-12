<?php

namespace App\Enums;

/**
 * El estado de una compra (docs/06). DERIVADO, no guardado: Borrador mientras
 * no tiene `finalizada_at`; despues, de sus reclamos: Con reclamo si hay alguno
 * abierto, Resuelta si todos se cerraron, Recibida si no tuvo ninguno. Asi no
 * puede contradecir a los reclamos.
 */
enum CompraEstado: string
{
    case Borrador = 'Borrador';
    case Recibida = 'Recibida';
    case ConReclamo = 'Con reclamo';
    case Resuelta = 'Resuelta';

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Borrador => 'bg-amber-100 text-amber-800',
            self::Recibida => 'bg-gray-100 text-gray-700',
            self::ConReclamo => 'bg-rose-100 text-rose-800',
            self::Resuelta => 'bg-green-100 text-green-800',
        };
    }

    public function badge(): string
    {
        return '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold ' . $this->badgeClasses() . '">' . e($this->value) . '</span>';
    }
}
