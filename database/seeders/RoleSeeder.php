<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Los tres roles de base. Sus permisos los da PermisoSeeder, desde
 * App\Support\CatalogoPermisos (la unica lista de permisos).
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'Administrador']);
        Role::firstOrCreate(['name' => 'Vendedor']);
        Role::firstOrCreate(['name' => 'Solo tecnicos']);
    }
}
