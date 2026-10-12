<?php

use App\Enums\ReservaEstado;
use App\Enums\SenaDestino;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un equipo apartado por un cliente que dejo una seña (docs/03). La
     * escribe solo ReservaService. No vence: se concreta en una venta (la seña
     * entra como pago, momento Sena) o se cancela, eligiendo si la seña se
     * devuelve o la retiene el negocio.
     *
     * Mientras esta Activa, el equipo esta en estado Reserva: no se vende a
     * otro ni sale en el catalogo.
     */
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('producto_id');
            $table->unsignedBigInteger('cliente_id');
            $table->decimal('sena', 12, 2);
            $table->unsignedBigInteger('metodo_pago_id');
            $table->enum('estado', ReservaEstado::values())->default(ReservaEstado::Activa->value);
            $table->enum('sena_destino', SenaDestino::values())->nullable();
            $table->unsignedBigInteger('venta_id')->nullable();
            $table->string('nota')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cerrada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cerrada_at')->nullable();
            $table->string('clave_idempotencia', 36)->nullable();
            // Una sola reserva ACTIVA por equipo: las cerradas dan NULL y el
            // UNIQUE las admite todas.
            $table->unsignedBigInteger('producto_activo')->nullable()
                ->storedAs("IF(estado = 'Activa', producto_id, NULL)");
            $table->timestamps();

            $table->foreign('producto_id', 'res_producto_fk')->references('id')->on('productos')->restrictOnDelete();
            $table->foreign('cliente_id', 'res_cliente_fk')->references('id')->on('clientes')->restrictOnDelete();
            $table->foreign('metodo_pago_id', 'res_metodo_fk')->references('id')->on('metodos_pago')->restrictOnDelete();
            $table->foreign('venta_id', 'res_venta_fk')->references('id')->on('ventas')->restrictOnDelete();

            $table->unique('producto_activo', 'reservas_producto_activo_unico');
            $table->unique('clave_idempotencia', 'reservas_clave_idem_unico');
            $table->index('estado', 'reservas_estado_idx');
        });

        DB::statement("ALTER TABLE reservas
            ADD CONSTRAINT res_sena_positiva CHECK (sena > 0),
            ADD CONSTRAINT res_cancelada_destino CHECK ((estado = 'Cancelada') = (sena_destino IS NOT NULL)),
            ADD CONSTRAINT res_concretada_venta CHECK ((estado = 'Concretada') = (venta_id IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};
