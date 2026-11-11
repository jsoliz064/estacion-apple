<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una descripcion libre del accesorio, como `productos.detalles` (pedido de
     * la tienda: «cable de 2 m, trenzado», «incluye vidrio»...).
     */
    public function up(): void
    {
        Schema::table('accesorios', function (Blueprint $table) {
            $table->text('detalles')->nullable()->after('marca');
        });
    }

    public function down(): void
    {
        Schema::table('accesorios', function (Blueprint $table) {
            $table->dropColumn('detalles');
        });
    }
};
