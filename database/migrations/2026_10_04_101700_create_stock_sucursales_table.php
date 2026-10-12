<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La unica verdad del stock de repuestos Y accesorios por sucursal.
     * `repuestos.cantidad` y `accesorios.cantidad` son solo su suma cacheada.
     *
     * Cada fila es de un repuesto O de un accesorio (CHECK), con FK reales en
     * vez de una relacion polimorfica: asi la base sigue impidiendo apuntar a
     * un articulo inexistente.
     *
     * La unicidad va con DOS indices compuestos: en MySQL los NULL no chocan en
     * un UNIQUE, asi que una fila de accesorio nunca colisiona en el indice de
     * repuestos y al reves. Por eso el INSERT ... ON DUPLICATE KEY UPDATE de
     * StockService::ingresar() sigue funcionando: en cada fila solo uno de los
     * dos puede coincidir.
     *
     * Las FK de articulo van en RESTRICT y no en cascade: MySQL prohibe
     * acciones referenciales sobre columnas que usa un CHECK. Un articulo con
     * stock se borra desde el codigo, que antes vacia sus filas.
     *
     * `cantidad` lleva signo a proposito: un descuadre se ve, no revienta.
     *
     * `minimo` es el stock minimo de ESE articulo en ESA sucursal (la tienda
     * pide 5 fundas, el Almacen 30). 0 = sin minimo. "Por agotarse" es una fila
     * con minimo > 0 y cantidad <= minimo. Lo escribe StockService::fijarMinimo;
     * los movimientos solo tocan `cantidad`.
     */
    public function up(): void
    {
        Schema::create('stock_sucursales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('repuesto_id')->nullable();
            $table->unsignedBigInteger('accesorio_id')->nullable();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->integer('cantidad')->default(0);
            $table->unsignedInteger('minimo')->default(0);
            $table->timestamps();

            $table->unique(['repuesto_id', 'sucursal_id'], 'ss_repuesto_sucursal_unico');
            $table->unique(['accesorio_id', 'sucursal_id'], 'ss_accesorio_sucursal_unico');
            $table->foreign('repuesto_id', 'ss_repuesto_fk')->references('id')->on('repuestos')->restrictOnDelete();
            $table->foreign('accesorio_id', 'ss_accesorio_fk')->references('id')->on('accesorios')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE stock_sucursales ADD CONSTRAINT ss_un_articulo
            CHECK ((repuesto_id IS NULL) <> (accesorio_id IS NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_sucursales');
    }
};
