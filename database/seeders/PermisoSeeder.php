<?php

namespace Database\Seeders;

use App\Support\CatalogoPermisos;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Lleva App\Support\CatalogoPermisos a la base. Seguro de volver a correr en
 * produccion (`php artisan db:seed --class=PermisoSeeder --force`):
 *
 *  - crea los permisos que falten y actualiza la descripcion de todos;
 *  - los roles por defecto se dan SOLO al crear el permiso. Antes era un
 *    syncRoles en cada corrida: volver a sembrar deshacia lo que el
 *    administrador habia cambiado a mano en Editar rol;
 *  - borra los obsoletos;
 *  - el Administrador recibe todos (en una base nueva no habria nadie con
 *    rol.edit para asignarselos).
 */
class PermisoSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::pluck('id', 'name');

        foreach (CatalogoPermisos::PERMISOS as $nombre => [$descripcion, $porDefecto]) {
            $permiso = Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
            $permiso->update(['descripcion' => $descripcion]);

            if ($permiso->wasRecentlyCreated) {
                $permiso->assignRole(array_values(array_filter(
                    array_map(fn($rol) => $roles[$rol] ?? null, $porDefecto)
                )));
            }
        }

        Permission::whereIn('name', CatalogoPermisos::OBSOLETOS)->get()->each->delete();

        Role::findByName('Administrador')->givePermissionTo(Permission::all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
