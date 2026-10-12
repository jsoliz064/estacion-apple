<?php

namespace App\Enums;

/**
 * Paleta de colores para repuestos.
 *
 * Fuente unica de verdad para los swatches del formulario y para el desplegable
 * del filtro de color en el modal selector de la venta.
 *
 * No se reutiliza ProductoColor a proposito: sus casos son colores de carcasa de
 * celular y respaldan una columna `enum` de MySQL en `productos`, asi que tocarlo
 * seria riesgo gratuito.
 *
 * Los hex se emiten con style inline (no hay safelist en tailwind.config.js, asi
 * que una clase dinamica tipo bg-[#xxx] nunca se generaria).
 */
enum RepuestoColor: string
{
    case Negro = 'Negro';
    case Blanco = 'Blanco';
    case Gris = 'Gris';
    case Plateado = 'Plateado';
    case Dorado = 'Dorado';
    case Rojo = 'Rojo';
    case Azul = 'Azul';
    case Celeste = 'Celeste';
    case Verde = 'Verde';
    case Amarillo = 'Amarillo';
    case Naranja = 'Naranja';
    case Rosado = 'Rosado';
    case Lila = 'Lila';
    case Morado = 'Morado';
    case Cafe = 'Cafe';
    case Natural = 'Natural';
    case Transparente = 'Transparente';

    public function hex(): string
    {
        return match ($this) {
            self::Negro => '#111827',
            self::Blanco => '#F9FAFB',
            self::Gris => '#9CA3AF',
            self::Plateado => '#D1D5DB',
            self::Dorado => '#D4AF37',
            self::Rojo => '#DC2626',
            self::Azul => '#2563EB',
            self::Celeste => '#38BDF8',
            self::Verde => '#16A34A',
            self::Amarillo => '#FACC15',
            self::Naranja => '#F97316',
            self::Rosado => '#F472B6',
            self::Lila => '#C084FC',
            self::Morado => '#7C3AED',
            self::Cafe => '#92400E',
            self::Natural => '#E7D3B7',
            self::Transparente => '#E5E7EB',
        };
    }

    /**
     * ['Negro' => '#111827', ...]
     *
     * @return array<string,string>
     */
    public static function palette(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $c) => [$c->value => $c->hex()])
            ->all();
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function hexFor(?string $nombre): ?string
    {
        return self::tryFrom((string) $nombre)?->hex();
    }
}
