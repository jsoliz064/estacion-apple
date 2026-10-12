<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            // CI o NIT.
            $table->string('ci', 20)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('correo')->nullable();
            $table->string('direccion')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            // Garantia de verdad contra el doble alta: la regla unique: del
            // formulario valida con un SELECT previo y dos pestanas la pasan.
            $table->unique('ci', 'clientes_ci_unico');
            $table->index('nombre', 'clientes_nombre_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
