<?php

namespace App\Enums;

use Illuminate\Support\Collection;

/**
 * Por que se dio de baja un equipo o unas unidades de stock.
 *
 * Los values van sin tildes (como 'Reparacion' en ProductoEstado): la colacion
 * utf8mb4_unicode_ci iguala tildes en un WHERE y un value con tilde invita a
 * que dos escrituras distintas cuenten como el mismo motivo. La tilde vive en
 * label().
 */
enum BajaMotivo: string
{
    case Dano = 'Dano';
    case Perdida = 'Perdida';
    case Robo = 'Robo';
    case Defecto = 'Defecto';
    case Otro = 'Otro';
    // El fallado que vuelve al proveedor al cerrar un reclamo (ReclamoService):
    // su costo queda en 0, asi que no suma en Perdidas. No se revierte.
    case Devolucion = 'Devolucion';

    public function label(): string
    {
        return match ($this) {
            self::Dano => 'Daño',
            self::Perdida => 'Pérdida',
            self::Robo => 'Robo',
            self::Defecto => 'Defecto de fábrica',
            self::Otro => 'Otro',
            self::Devolucion => 'Devuelto al proveedor',
        };
    }

    public static function labelDe(?string $motivo): string
    {
        return self::tryFrom((string) $motivo)?->label() ?? (string) $motivo;
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function toSelectArray(): Collection
    {
        return collect(self::manuales())->mapWithKeys(fn($c) => [$c->value => $c->label()]);
    }

    /**
     * Los que se eligen a mano al dar de baja. Devolucion no: la escribe el
     * cierre de un reclamo, con el costo en 0.
     *
     * @return self[]
     */
    public static function manuales(): array
    {
        return array_values(array_filter(self::cases(), fn($c) => $c !== self::Devolucion));
    }

    /** @return array<int,string> */
    public static function valoresManuales(): array
    {
        return array_column(self::manuales(), 'value');
    }
}
