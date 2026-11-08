<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El tipo de cambio del dia (Bs por USD), que se actualiza a mano. Es un
 * historial: el vigente es la ultima fila. Todo el dinero se guarda en USD y
 * los Bs son solo una vista (USD x este valor), asi que actualizarlo no
 * recalcula nada guardado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_cambio', function (Blueprint $table) {
            $table->id();
            $table->decimal('valor', 10, 4);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE tipos_cambio ADD CONSTRAINT tc_valor_positivo CHECK (valor > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_cambio');
    }
};
