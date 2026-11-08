<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los colores del equipo. Antes eran un enum de PHP que ademas fijaba la
     * columna enum de `productos`: cada color nuevo que saca Apple exigia
     * tocar codigo y migrar.
     *
     * `productos.color` guarda el NOMBRE (texto), no una FK: lo pintan notas,
     * recibos, tablas y reportes, y lo agrupan los filtros. Por eso un color
     * en uso no se renombra ni se borra: se desactiva (ver Color).
     *
     * `productos_modelos_colores` marca los colores de cada modelo: el alta
     * ofrece solo esos, o todos los activos si el modelo no tiene ninguno.
     */
    public function up(): void
    {
        Schema::create('colores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50);
            // La muestra (#RRGGBB): se pinta con style inline, no hay safelist.
            $table->char('hex', 7)->default('#9CA3AF');
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->unique('nombre', 'colores_nombre_unico');
        });

        Schema::create('productos_modelos_colores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_modelo_id')->constrained('productos_modelos')->cascadeOnDelete();
            $table->foreignId('color_id')->constrained('colores')->cascadeOnDelete();

            $table->unique(['producto_modelo_id', 'color_id'], 'pmc_modelo_color_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos_modelos_colores');
        Schema::dropIfExists('colores');
    }
};
