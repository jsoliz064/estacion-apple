<?php

use App\Enums\Moneda;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            // Codigo de barras; un vacio se guarda como NULL (NormalizaCodigosTrait).
            $table->string('upc', 50)->nullable();
            $table->string('fabricante')->nullable();
            $table->string('color', 50)->nullable();
            $table->string('color_hex', 10)->nullable();
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
            $table->foreignId('producto_modelo_id')->nullable()->constrained('productos_modelos')->nullOnDelete();
            $table->foreignId('repuesto_categoria_id')->nullable()->constrained('repuestos_categorias')->nullOnDelete();
            $table->timestamps();

            $table->index('nombre', 'repuestos_nombre_index');
            $table->index('upc', 'repuestos_upc_index');
        });

        // Como productos_montos_moneda.
        DB::statement("ALTER TABLE repuestos ADD CONSTRAINT rep_montos_moneda CHECK (
            (moneda = 'USD' AND costo_moneda_monto IS NULL AND precio_moneda_monto IS NULL AND tipo_cambio IS NULL)
            OR (moneda = 'BOB' AND costo_moneda_monto IS NOT NULL AND precio_moneda_monto IS NOT NULL
                AND tipo_cambio IS NOT NULL AND tipo_cambio > 0))");
    }

    public function down(): void
    {
        Schema::dropIfExists('repuestos');
    }
};
