<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Piezas de reparacion. Los accesorios viven en su propia tabla
     * (`accesorios`): no tienen modelo, fabricante ni categoria de pieza.
     * Los dos comparten el stock por sucursal (stock_sucursales).
     */
    public function up(): void
    {
        Schema::create('repuestos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            // Codigo libre; un vacio se guarda como NULL (NormalizaCodigosTrait).
            $table->string('sku', 50)->nullable();
            $table->string('upc', 50)->nullable();
            $table->string('fabricante')->nullable();
            $table->string('color', 50)->nullable();
            $table->string('color_hex', 10)->nullable();
            // En pantalla se rotula "Costo (código)" (pedido del cliente).
            $table->decimal('costo', 10, 2)->default(0);
            $table->decimal('precio', 10, 2)->default(0);
            // Total cacheado: la suma de stock_sucursales. Solo lo escribe
            // StockService::recalcularTotales().
            $table->integer('cantidad')->default(0);
            $table->foreignId('producto_modelo_id')->nullable()->constrained('productos_modelos')->nullOnDelete();
            $table->foreignId('repuesto_categoria_id')->nullable()->constrained('repuestos_categorias')->nullOnDelete();
            $table->timestamps();

            $table->index('nombre', 'repuestos_nombre_index');
            $table->unique('sku', 'repuestos_sku_unico');
            $table->index('upc', 'repuestos_upc_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repuestos');
    }
};
