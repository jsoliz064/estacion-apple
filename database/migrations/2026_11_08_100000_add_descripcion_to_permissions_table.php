<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una frase de que deja hacer cada permiso, para la pantalla de roles: el
     * nombre tecnico (producto.edit) no le dice nada al administrador. La
     * llena PermisoSeeder desde App\Support\CatalogoPermisos.
     */
    public function up(): void
    {
        Schema::table(config('permission.table_names.permissions'), function (Blueprint $table) {
            $table->string('descripcion', 255)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table(config('permission.table_names.permissions'), function (Blueprint $table) {
            $table->dropColumn('descripcion');
        });
    }
};
