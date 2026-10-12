<?php

use App\Enums\Moneda;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lo pagado al proveedor por cada compra: al recibirla y despues (cuentas
     * por pagar). Lo escribe SOLO PagoProveedorService, que cachea la suma en
     * compras.pagado. Es el espejo de ventas_pagos: `monto` siempre en Bs, y un
     * pago en USD guarda los dolares y la tasa.
     */
    public function up(): void
    {
        Schema::create('compras_pagos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('compra_id');
            $table->unsignedBigInteger('metodo_pago_id');
            $table->decimal('monto', 12, 2);
            $table->enum('moneda', Moneda::values())->default(Moneda::BOB->value);
            $table->decimal('monto_moneda', 12, 2)->nullable();
            $table->decimal('tipo_cambio', 10, 4)->nullable();
            $table->boolean('al_recibir')->default(false);
            $table->dateTime('fecha');
            $table->string('nota')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('clave_idempotencia', 36)->nullable();
            $table->timestamps();

            $table->foreign('compra_id', 'cp_compra_fk')->references('id')->on('compras')->restrictOnDelete();
            $table->foreign('metodo_pago_id', 'cp_metodo_fk')->references('id')->on('metodos_pago')->restrictOnDelete();

            $table->unique(['clave_idempotencia', 'compra_id', 'metodo_pago_id', 'moneda'], 'cp_clave_idem_compra_metodo_unico');
            $table->index('fecha', 'cp_fecha_idx');
        });

        DB::statement("ALTER TABLE compras_pagos
            ADD CONSTRAINT cp_monto_positivo CHECK (monto > 0),
            ADD CONSTRAINT cp_moneda_datos CHECK (
                (moneda = 'BOB' AND monto_moneda IS NULL AND tipo_cambio IS NULL)
                OR (moneda = 'USD' AND monto_moneda > 0 AND tipo_cambio > 0))");
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_pagos');
    }
};
