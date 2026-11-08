<?php

use App\Enums\Moneda;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            $table->string('upc', 50)->nullable();
            $table->string('marca')->nullable();
            // En USD. En pantalla se rotula "Costo (código)" (pedido del cliente).
            $table->decimal('costo', 10, 2)->default(0);
            $table->decimal('precio', 10, 2)->default(0);
            // Cargados en Bs, con un tipo de cambio para los dos: como el equipo
            // (MontosEnMonedaTrait).
            $table->enum('moneda', Moneda::values())->default(Moneda::USD->value);
            $table->decimal('costo_moneda_monto', 12, 2)->nullable();
            $table->decimal('precio_moneda_monto', 12, 2)->nullable();
            $table->decimal('tipo_cambio', 10, 4)->nullable();
            // Total cacheado: la suma de stock_sucursales. Solo lo escribe
            // StockService::recalcularTotales().
            $table->integer('cantidad')->default(0);
            $table->unsignedBigInteger('accesorio_categoria_id')->nullable();
            $table->timestamps();

            $table->index('nombre', 'accesorios_nombre_index');
            $table->index('upc', 'accesorios_upc_index');
            $table->foreign('accesorio_categoria_id', 'accesorios_categoria_fk')
                ->references('id')->on('accesorios_categorias')->nullOnDelete();
        });

        // Como productos_montos_moneda.
        DB::statement("ALTER TABLE accesorios ADD CONSTRAINT acc_montos_moneda CHECK (
            (moneda = 'USD' AND costo_moneda_monto IS NULL AND precio_moneda_monto IS NULL AND tipo_cambio IS NULL)
            OR (moneda = 'BOB' AND costo_moneda_monto IS NOT NULL AND precio_moneda_monto IS NOT NULL
                AND tipo_cambio IS NOT NULL AND tipo_cambio > 0))");
    }

    public function down(): void
    {
        Schema::dropIfExists('accesorios');
    }
};
