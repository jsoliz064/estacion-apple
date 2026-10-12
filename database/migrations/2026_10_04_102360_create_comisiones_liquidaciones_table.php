<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El pago de comisiones a una persona (docs/05): un vendedor (user_id) o un
     * tecnico (tecnico_id), por un periodo. Las comisiones que cubre la apuntan
     * con comisiones.liquidacion_id. Lo escribe SOLO ComisionService.
     */
    public function up(): void
    {
        Schema::create('comisiones_liquidaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('tecnico_id')->nullable();
            $table->date('desde');
            $table->date('hasta');
            $table->decimal('total', 12, 2);
            $table->unsignedInteger('cantidad');
            $table->string('nota')->nullable();
            $table->foreignId('pagado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('clave_idempotencia', 36)->nullable()->unique('cl_clave_idem_unico');
            $table->timestamps();

            // RESTRICT: el beneficiario no se borra con pagos registrados.
            $table->foreign('user_id', 'cl_user_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('tecnico_id', 'cl_tecnico_fk')->references('id')->on('tecnicos')->restrictOnDelete();
            $table->index('created_at', 'cl_creado_idx');
        });

        DB::statement("ALTER TABLE comisiones_liquidaciones
            ADD CONSTRAINT cl_un_beneficiario CHECK ((user_id IS NULL) <> (tecnico_id IS NULL)),
            ADD CONSTRAINT cl_periodo CHECK (desde <= hasta),
            ADD CONSTRAINT cl_total_positivo CHECK (total >= 0 AND cantidad > 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('comisiones_liquidaciones');
    }
};
