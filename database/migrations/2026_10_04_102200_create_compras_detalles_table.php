<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lo que trajo cada compra. Una linea es un equipo (creado por esa compra,
     * cantidad 1), un repuesto o un accesorio (con cantidad). La escribe solo
     * CompraService.
     *
     * - `tipo` es GENERADA desde la FK que no es NULL: no puede contradecirla.
     * - `articulo_clave` es la clave natural de la linea dentro de la compra
     *   (P/R/A + id): editar y reintentar choca con el UNIQUE en vez de
     *   duplicar la linea y mover el stock dos veces.
     * - `producto_id` UNIQUE: la linea es la unica verdad de que compra trajo
     *   el equipo (productos ya no tiene compra_id).
     *
     * Todas las FK de articulo y la de cabecera van en RESTRICT: MySQL prohibe
     * acciones referenciales sobre columnas que usan un CHECK o una columna
     * generada STORED. Y es lo correcto: una compra se anula por el servicio,
     * que devuelve el stock, nunca por una cascada que lo olvidaria.
     */
    public function up(): void
    {
        Schema::create('compras_detalles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('compra_id');
            $table->unsignedBigInteger('producto_id')->nullable();
            $table->unsignedBigInteger('repuesto_id')->nullable();
            $table->unsignedBigInteger('accesorio_id')->nullable();
            $table->string('tipo', 10)->storedAs(
                "CASE WHEN producto_id IS NOT NULL THEN 'Producto'
                      WHEN repuesto_id IS NOT NULL THEN 'Repuesto'
                      WHEN accesorio_id IS NOT NULL THEN 'Accesorio' END"
            );
            $table->string('articulo_clave', 24)->storedAs(
                "CASE WHEN producto_id IS NOT NULL THEN CONCAT('P', producto_id)
                      WHEN repuesto_id IS NOT NULL THEN CONCAT('R', repuesto_id)
                      ELSE CONCAT('A', accesorio_id) END"
            );
            // Sucursal a la que entro el stock, congelada en la linea.
            $table->unsignedBigInteger('sucursal_id')->nullable();
            $table->unsignedInteger('cantidad')->default(1);
            $table->decimal('costo', 10, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            // Solo en la linea de un equipo de una compra en BORRADOR: el
            // estado elegido al cargarlo (Inventario, Fuera, Roto). El equipo
            // espera en EnCompra y finalizar() lo pasa a este estado.
            $table->string('estado_destino', 20)->nullable();
            $table->timestamps();

            $table->foreign('compra_id', 'cd_compra_fk')->references('id')->on('compras')->restrictOnDelete();
            $table->foreign('producto_id', 'cd_producto_fk')->references('id')->on('productos')->restrictOnDelete();
            $table->foreign('repuesto_id', 'cd_repuesto_fk')->references('id')->on('repuestos')->restrictOnDelete();
            $table->foreign('accesorio_id', 'cd_accesorio_fk')->references('id')->on('accesorios')->restrictOnDelete();
            $table->foreign('sucursal_id', 'cd_sucursal_fk')->references('id')->on('sucursales')->nullOnDelete();

            $table->unique('producto_id', 'compras_detalles_producto_unico');
            $table->unique(['compra_id', 'articulo_clave'], 'cd_compra_articulo_unico');
            $table->index('tipo', 'cd_tipo_index');
        });

        DB::statement('ALTER TABLE compras_detalles
            ADD CONSTRAINT cd_un_articulo CHECK ((producto_id IS NOT NULL) + (repuesto_id IS NOT NULL) + (accesorio_id IS NOT NULL) = 1),
            ADD CONSTRAINT cd_producto_unidad CHECK (producto_id IS NULL OR cantidad = 1),
            ADD CONSTRAINT cd_cantidad_positiva CHECK (cantidad >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_detalles');
    }
};
