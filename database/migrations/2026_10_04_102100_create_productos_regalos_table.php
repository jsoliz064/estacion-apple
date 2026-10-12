<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accesorios que se entregan de regalo con un equipo (reemplaza al viejo
     * "costo de envio"). Salen del stock y su costo se suma al costo del equipo
     * (productos.costo_regalos, cacheado por Producto::recalcularCosto()).
     *
     * El costo y la sucursal de origen se congelan: quitar el regalo despues
     * devuelve el stock a esa sucursal, no a la que tenga el equipo ahora.
     */
    public function up(): void
    {
        Schema::create('productos_regalos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('producto_id');
            $table->unsignedBigInteger('accesorio_id');
            $table->unsignedBigInteger('sucursal_id')->nullable();
            $table->unsignedInteger('cantidad');
            $table->decimal('costo', 10, 2)->default(0);
            $table->decimal('subtotal_costo', 10, 2)->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['producto_id', 'accesorio_id'], 'productos_regalos_unico');
            $table->foreign('producto_id', 'pr_producto_fk')->references('id')->on('productos')->restrictOnDelete();
            $table->foreign('accesorio_id', 'pr_accesorio_fk')->references('id')->on('accesorios')->restrictOnDelete();
            $table->foreign('sucursal_id', 'pr_sucursal_fk')->references('id')->on('sucursales')->nullOnDelete();
        });

        DB::statement('ALTER TABLE productos_regalos ADD CONSTRAINT pr_cantidad_positiva CHECK (cantidad >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('productos_regalos');
    }
};
