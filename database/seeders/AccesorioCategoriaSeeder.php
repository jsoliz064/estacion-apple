<?php

namespace Database\Seeders;

use App\Models\AccesorioCategoria;
use Illuminate\Database\Seeder;

/** Categorias de accesorio mas comunes. firstOrCreate: se puede volver a correr. */
class AccesorioCategoriaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Fundas', 'Vidrios templados', 'Cargadores', 'Cables', 'Audífonos', 'Soportes', 'Otros'] as $nombre) {
            AccesorioCategoria::firstOrCreate(['nombre' => $nombre]);
        }
    }
}
