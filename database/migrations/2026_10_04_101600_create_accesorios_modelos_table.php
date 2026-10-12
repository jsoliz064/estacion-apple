<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Con que modelos de telefono es compatible un accesorio (una funda, un vidrio). */
    public function up(): void
    {
        Schema::create('accesorios_modelos', function (Blueprint $table) {
            $table->unsignedBigInteger('accesorio_id');
            $table->unsignedBigInteger('producto_modelo_id');

            $table->primary(['accesorio_id', 'producto_modelo_id']);
            $table->index('producto_modelo_id', 'am_modelo_index');
            $table->foreign('accesorio_id', 'am_accesorio_fk')
                ->references('id')->on('accesorios')->cascadeOnDelete();
            $table->foreign('producto_modelo_id', 'am_modelo_fk')
                ->references('id')->on('productos_modelos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accesorios_modelos');
    }
};
