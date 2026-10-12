<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla polimorfica unica para todo lo que una persona hizo. La escribe
     * BitacoraObserver; es inmutable (el modelo lanza excepcion al editar o
     * borrar) y no tiene updated_at.
     *
     * `auditable_id` NO tiene FK a proposito: borrar el sujeto no se lleva su
     * historia. Las FK de contexto van en set null, con nombre propio porque
     * MySQL corta los identificadores a 64 caracteres.
     */
    public function up(): void
    {
        Schema::create('bitacoras', function (Blueprint $table) {
            $table->id();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->string('evento', 40);
            $table->text('descripcion')->nullable();
            $table->json('cambios')->nullable();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('venta_id')->nullable();
            $table->unsignedBigInteger('compra_id')->nullable();
            $table->unsignedBigInteger('producto_reparacion_id')->nullable();
            $table->unsignedBigInteger('repuesto_id')->nullable();
            $table->unsignedBigInteger('accesorio_id')->nullable();
            $table->unsignedBigInteger('sucursal_id')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['auditable_type', 'auditable_id', 'id'], 'bitacoras_auditable_index');
            $table->index(['user_id', 'id'], 'bitacoras_usuario_index');
            $table->index('evento', 'bitacoras_evento_index');

            $table->foreign('user_id', 'bitacoras_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('venta_id', 'bitacoras_venta_fk')->references('id')->on('ventas')->nullOnDelete();
            $table->foreign('compra_id', 'bitacoras_compra_fk')->references('id')->on('compras')->nullOnDelete();
            $table->foreign('producto_reparacion_id', 'bitacoras_reparacion_fk')->references('id')->on('productos_reparaciones')->nullOnDelete();
            $table->foreign('repuesto_id', 'bitacoras_repuesto_fk')->references('id')->on('repuestos')->nullOnDelete();
            $table->foreign('accesorio_id', 'bitacoras_accesorio_fk')->references('id')->on('accesorios')->nullOnDelete();
            $table->foreign('sucursal_id', 'bitacoras_sucursal_fk')->references('id')->on('sucursales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacoras');
    }
};
