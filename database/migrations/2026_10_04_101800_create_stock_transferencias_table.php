<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Traspasos de stock entre sucursales, de un repuesto O de un accesorio.
     * Una transferencia son dos filas en el historial (Salida en el origen,
     * Entrada en el destino), neta cero. Ver stock_sucursales para las FK.
     */
    public function up(): void
    {
        Schema::create('stock_transferencias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('repuesto_id')->nullable();
            $table->unsignedBigInteger('accesorio_id')->nullable();
            $table->unsignedBigInteger('sucursal_origen_id');
            $table->unsignedBigInteger('sucursal_destino_id');
            $table->unsignedInteger('cantidad');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('repuesto_id', 'st_repuesto_fk')->references('id')->on('repuestos')->restrictOnDelete();
            $table->foreign('accesorio_id', 'st_accesorio_fk')->references('id')->on('accesorios')->restrictOnDelete();
            $table->foreign('sucursal_origen_id', 'st_origen_fk')->references('id')->on('sucursales')->restrictOnDelete();
            $table->foreign('sucursal_destino_id', 'st_destino_fk')->references('id')->on('sucursales')->restrictOnDelete();
            $table->index(['repuesto_id', 'created_at'], 'st_repuesto_fecha_index');
            $table->index(['accesorio_id', 'created_at'], 'st_accesorio_fecha_index');
        });

        DB::statement('ALTER TABLE stock_transferencias
            ADD CONSTRAINT st_un_articulo CHECK ((repuesto_id IS NULL) <> (accesorio_id IS NULL)),
            ADD CONSTRAINT st_origen_distinto CHECK (sucursal_origen_id <> sucursal_destino_id),
            ADD CONSTRAINT st_cantidad_positiva CHECK (cantidad >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transferencias');
    }
};
