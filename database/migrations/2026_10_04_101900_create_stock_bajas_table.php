<?php

use App\Enums\BajaMotivo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unidades de un repuesto o accesorio dadas de baja (perdidas, danadas,
     * robadas...). La escribe BajaService junto con el retiro de stock. El costo
     * unitario se congela para el reporte de perdidas.
     */
    public function up(): void
    {
        Schema::create('stock_bajas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('repuesto_id')->nullable();
            $table->unsignedBigInteger('accesorio_id')->nullable();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            $table->decimal('costo', 10, 2)->default(0);
            $table->enum('motivo', BajaMotivo::values());
            $table->string('nota')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('repuesto_id', 'sb_repuesto_fk')->references('id')->on('repuestos')->restrictOnDelete();
            $table->foreign('accesorio_id', 'sb_accesorio_fk')->references('id')->on('accesorios')->restrictOnDelete();
            $table->index('created_at', 'sb_fecha_index');
        });

        DB::statement('ALTER TABLE stock_bajas
            ADD CONSTRAINT sb_un_articulo CHECK ((repuesto_id IS NULL) <> (accesorio_id IS NULL)),
            ADD CONSTRAINT sb_cantidad_positiva CHECK (cantidad >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_bajas');
    }
};
