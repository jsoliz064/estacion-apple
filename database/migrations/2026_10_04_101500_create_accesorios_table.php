<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accesorios: se venden sueltos, con el telefono o se regalan con el.
     * Comparten con los repuestos el stock por sucursal (stock_sucursales).
     */
    public function up(): void
    {
        Schema::create('accesorios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('sku', 50)->nullable();
            $table->string('upc', 50)->nullable();
            $table->string('marca')->nullable();
            // En pantalla se rotula "Costo (código)" (pedido del cliente).
            $table->decimal('costo', 10, 2)->default(0);
            $table->decimal('precio', 10, 2)->default(0);
            // Total cacheado: la suma de stock_sucursales. Solo lo escribe
            // StockService::recalcularTotales().
            $table->integer('cantidad')->default(0);
            $table->unsignedBigInteger('accesorio_categoria_id')->nullable();
            $table->timestamps();

            $table->index('nombre', 'accesorios_nombre_index');
            $table->unique('sku', 'accesorios_sku_unico');
            $table->index('upc', 'accesorios_upc_index');
            $table->foreign('accesorio_categoria_id', 'accesorios_categoria_fk')
                ->references('id')->on('accesorios_categorias')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accesorios');
    }
};
