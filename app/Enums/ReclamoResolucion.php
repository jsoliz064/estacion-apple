<?php

namespace App\Enums;

/** Como se cerro un reclamo al proveedor (decision del usuario: las tres). */
enum ReclamoResolucion: string
{
    // El proveedor mando otro equipo: entra en la misma compra con el costo del fallado.
    case Reemplazo = 'Reemplazo';
    // El proveedor descuenta: el fallado vuelve y su costo sale del total.
    case Descuento = 'Descuento';
    // Se queda el equipo (reparado o tal cual): vuelve a Inventario o a Roto.
    case Aceptado = 'Aceptado';

    public function label(): string
    {
        return match ($this) {
            self::Reemplazo => 'Reemplazado por el proveedor',
            self::Descuento => 'Descontado por el proveedor',
            self::Aceptado => 'Aceptado como está',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
