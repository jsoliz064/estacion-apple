<?php

namespace Database\Seeders;

use App\Models\MetodoPago;
use Illuminate\Database\Seeder;

/** Los metodos de pago de arranque (docs/00). firstOrCreate: se puede volver a correr. */
class MetodoPagoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Efectivo', 'QR'] as $orden => $nombre) {
            MetodoPago::firstOrCreate(['nombre' => $nombre], ['orden' => $orden + 1, 'activo' => true]);
        }

        // De sistema: el equipo recibido en permuta es el pago. No se ofrece al
        // cobrar a mano ni se edita.
        MetodoPago::firstOrCreate(['nombre' => MetodoPago::PERMUTA], ['orden' => 99, 'activo' => true, 'sistema' => true]);
    }
}
