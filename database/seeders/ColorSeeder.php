<?php

namespace Database\Seeders;

use App\Models\Color;
use Illuminate\Database\Seeder;

/**
 * Los colores con los que arranca el sistema (los del viejo enum
 * ProductoColor), con su muestra. No marca colores por modelo: sin marcas, el
 * alta ofrece todos los activos, y el negocio marca los de cada modelo desde
 * Modelos > Colores. firstOrCreate por nombre: se puede volver a correr.
 */
class ColorSeeder extends Seeder
{
    private const COLORES = [
        'Negro' => '#1F2937',
        'Blanco' => '#F3F4F6',
        'Azul' => '#2563EB',
        'Celeste' => '#7DD3FC',
        'Cielo' => '#BFDBFE',
        'Verde' => '#86EFAC',
        'Amarillo' => '#FDE047',
        'Naranja' => '#F97316',
        'Rojo' => '#DC2626',
        'Rosado' => '#F9A8D4',
        'Lila' => '#C4B5FD',
        'Vino' => '#7F1D1D',
        'Natural' => '#D6CFC4',
        'Desert' => '#C8A27C',
    ];

    public function run(): void
    {
        $orden = 0;
        foreach (self::COLORES as $nombre => $hex) {
            Color::firstOrCreate(['nombre' => $nombre], ['hex' => $hex, 'orden' => $orden++]);
        }
    }
}
