<?php

use App\Enums\ComisionOrigen;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La comision de una venta (para su vendedor) o de una reparacion (para su
     * tecnico). Una fila por documento, aunque el monto sea 0: asi el % queda
     * congelado el dia que nacio. Lo escribe SOLO ComisionService.
     *
     * Estado DERIVADO (ComisionEstado): ganada_at NULL = pendiente (la venta no
     * esta pagada, la reparacion no termino); con fecha y sin liquidacion = por
     * pagar; con liquidacion_id = pagada.
     *
     * venta_id y producto_reparacion_id van en SET NULL y fuera de todo CHECK
     * (MySQL no deja acciones referenciales en columnas de un CHECK): anular
     * una venta cuya comision ya se pago no se lleva el registro del pago; la
     * fila queda con su `referencia` congelada.
     */
    public function up(): void
    {
        Schema::create('comisiones', function (Blueprint $table) {
            $table->id();
            $table->enum('origen', ComisionOrigen::values());
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('tecnico_id')->nullable();
            $table->foreignId('venta_id')->nullable()->unique('com_venta_unico')
                ->constrained('ventas')->nullOnDelete();
            $table->foreignId('producto_reparacion_id')->nullable()->unique('com_reparacion_unico')
                ->constrained('productos_reparaciones')->nullOnDelete();
            $table->string('referencia');
            // Ganancia de la venta (puede ser negativa) o mano de obra.
            $table->decimal('base', 12, 2);
            $table->decimal('porcentaje', 5, 2);
            $table->decimal('monto', 12, 2);
            $table->timestamp('ganada_at')->nullable();
            $table->unsignedBigInteger('liquidacion_id')->nullable();
            $table->timestamps();

            $table->foreign('user_id', 'com_user_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('tecnico_id', 'com_tecnico_fk')->references('id')->on('tecnicos')->restrictOnDelete();
            $table->foreign('liquidacion_id', 'com_liquidacion_fk')->references('id')->on('comisiones_liquidaciones')->restrictOnDelete();
            $table->index('ganada_at', 'com_ganada_idx');
        });

        DB::statement("ALTER TABLE comisiones
            ADD CONSTRAINT com_un_beneficiario CHECK ((user_id IS NULL) <> (tecnico_id IS NULL)),
            ADD CONSTRAINT com_monto_rango CHECK (monto >= 0 AND porcentaje BETWEEN 0 AND 100),
            ADD CONSTRAINT com_pagada_ganada CHECK (liquidacion_id IS NULL OR ganada_at IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('comisiones');
    }
};
