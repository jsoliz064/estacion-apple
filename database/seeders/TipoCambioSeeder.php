<?php

namespace Database\Seeders;

use App\Models\TipoCambio;
use Illuminate\Database\Seeder;

/** El primer tipo de cambio: sin uno, los Bs saldrian del respaldo fijo. */
class TipoCambioSeeder extends Seeder
{
    public function run(): void
    {
        if (!TipoCambio::exists()) {
            TipoCambio::create(['valor' => TipoCambio::RESPALDO]);
        }
    }
}
