<?php

use App\Enums\ReclamoEstado;
use App\Enums\ReclamoResolucion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un equipo fallado reclamado al proveedor (docs/06). Lo escribe SOLO
     * ReclamoService. Mientras esta Abierto, el equipo esta en estado Reclamo.
     * Se cierra con Reemplazo (otro equipo en la misma compra, con el costo del
     * fallado), Descuento (el fallado vuelve y su costo sale de la compra) o
     * Aceptado (se queda el equipo).
     *
     * El estado de la compra (Recibida / Con reclamo / Resuelta) se DERIVA de
     * estas filas: no hay columna que pueda contradecirlas.
     */
    public function up(): void
    {
        Schema::create('compras_reclamos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('compra_id');
            $table->unsignedBigInteger('producto_id');
            $table->string('motivo');
            $table->enum('estado', ReclamoEstado::values())->default(ReclamoEstado::Abierto->value);
            $table->enum('resolucion', ReclamoResolucion::values())->nullable();
            $table->unsignedBigInteger('producto_reemplazo_id')->nullable();
            $table->string('nota_cierre')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cerrado_at')->nullable();
            // Un solo reclamo ABIERTO por equipo.
            $table->unsignedBigInteger('producto_abierto')->nullable()
                ->storedAs("IF(estado = 'Abierto', producto_id, NULL)");
            $table->timestamps();

            $table->foreign('compra_id', 'crc_compra_fk')->references('id')->on('compras')->restrictOnDelete();
            $table->foreign('producto_id', 'crc_producto_fk')->references('id')->on('productos')->restrictOnDelete();
            $table->foreign('producto_reemplazo_id', 'crc_reemplazo_fk')->references('id')->on('productos')->restrictOnDelete();

            $table->unique('producto_abierto', 'crc_producto_abierto_unico');
            $table->unique('producto_reemplazo_id', 'crc_reemplazo_unico');
            $table->index('estado', 'crc_estado_idx');
        });

        DB::statement("ALTER TABLE compras_reclamos
            ADD CONSTRAINT crc_resuelto_resolucion CHECK ((estado = 'Resuelto') = (resolucion IS NOT NULL)),
            ADD CONSTRAINT crc_reemplazo_equipo CHECK ((resolucion = 'Reemplazo') = (producto_reemplazo_id IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_reclamos');
    }
};
