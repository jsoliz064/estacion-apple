<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El almacenamiento pasa a ser opcional: un Apple Watch no tiene, y no se
     * podia cargar (decision del usuario: opcional, y el tipo de equipo lo dice
     * la categoria del modelo, sin un atributo nuevo).
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('almacenamiento')->nullable()->change();
        });

        DB::statement("UPDATE productos SET almacenamiento = NULL WHERE TRIM(almacenamiento) = ''");
    }

    public function down(): void
    {
        DB::statement("UPDATE productos SET almacenamiento = '' WHERE almacenamiento IS NULL");

        Schema::table('productos', function (Blueprint $table) {
            $table->string('almacenamiento')->nullable(false)->change();
        });
    }
};
