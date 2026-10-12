<?php

namespace Database\Seeders;

use App\Models\Sucursal;
use Illuminate\Database\Seeder;

class SucursalSeeder extends Seeder
{
    /**
     * Siembra el Almacen: es obligatorio (Sucursal::ALMACEN) porque la
     * reparacion terminada muda ahi el equipo. Antes lo creaba una migracion
     * de datos; las sucursales de venta las da de alta el negocio desde su
     * pantalla.
     */
    public function run(): void
    {
        Sucursal::firstOrCreate(['nombre' => Sucursal::ALMACEN]);
    }
}
