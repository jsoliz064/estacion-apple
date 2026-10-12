<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Va primero de todas: productos, ventas y compras apuntan a ella.
     *
     * 'Almacen' no se crea aqui sino en SucursalSeeder. Es obligatoria (la
     * reparacion terminada se muda ahi, ver Sucursal::almacenId()), y
     * sembrarla desde una migracion mezclaba datos con esquema.
     */
    public function up(): void
    {
        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            // Se imprimen en la nota de venta.
            $table->string('direccion')->nullable();
            $table->string('telefono', 30)->nullable();
            // Una sucursal con movimientos no se borra: se desactiva y deja de
            // ofrecerse al cargar productos o ventas.
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sucursales');
    }
};
