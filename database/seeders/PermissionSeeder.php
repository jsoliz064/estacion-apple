<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        //REPUESTOS CATEGORIAS
        Permission::firstOrCreate(['name' => 'repuesto-categoria.index']);
        Permission::firstOrCreate(['name' => 'repuesto-categoria.create']);
        Permission::firstOrCreate(['name' => 'repuesto-categoria.edit']);
        Permission::firstOrCreate(['name' => 'repuesto-categoria.delete']);

        //PRODUCTOS
        // Sueltos a proposito: se asignan a mano desde Editar Rol.
        Permission::firstOrCreate(['name' => 'producto.estado-masivo']);
        Permission::firstOrCreate(['name' => 'producto.trabajo-externo']);

        //REPUESTOS
        Permission::firstOrCreate(['name' => 'repuesto.historial']);
        Permission::firstOrCreate(['name' => 'repuesto.transferir']);

        //ACCESORIOS
        Permission::firstOrCreate(['name' => 'accesorio.index']);
        Permission::firstOrCreate(['name' => 'accesorio.create']);
        Permission::firstOrCreate(['name' => 'accesorio.edit']);
        Permission::firstOrCreate(['name' => 'accesorio.delete']);
        Permission::firstOrCreate(['name' => 'accesorio.historial']);
        Permission::firstOrCreate(['name' => 'accesorio.transferir']);
        Permission::firstOrCreate(['name' => 'accesorio.baja']);

        //ACCESORIOS CATEGORIAS
        Permission::firstOrCreate(['name' => 'accesorio-categoria.index']);
        Permission::firstOrCreate(['name' => 'accesorio-categoria.create']);
        Permission::firstOrCreate(['name' => 'accesorio-categoria.edit']);
        Permission::firstOrCreate(['name' => 'accesorio-categoria.delete']);

        //CLIENTES
        // El modulo nace entero aqui: hasta ahora el cliente era un texto suelto
        // en cada venta y no habia nada que permisar.
        Permission::firstOrCreate(['name' => 'cliente.index']);
        Permission::firstOrCreate(['name' => 'cliente.create']);
        Permission::firstOrCreate(['name' => 'cliente.edit']);
        Permission::firstOrCreate(['name' => 'cliente.delete']);
        Permission::firstOrCreate(['name' => 'cliente.historial']);

        //USUARIOS
        // Ver todo lo que hizo un usuario, leido de la bitacora. Suelto, como el
        // resto: se asigna a mano desde Editar Rol.
        Permission::firstOrCreate(['name' => 'user.historial']);

        // El Administrador recibe TODOS los permisos, incluidos los sueltos de
        // arriba: en una base recien creada no habria nadie con rol.edit que
        // pudiera asignarselos, y el primer usuario no veria accesorios ni
        // clientes. Los demas roles se arman a mano desde Editar Rol.
        Role::findByName('Administrador')->givePermissionTo(Permission::all());
    }
}
