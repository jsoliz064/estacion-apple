<?php

use App\Enums\ProductoTipoVenta;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lo que se vendio en cada venta. Una linea es un equipo (cantidad 1, con
     * garantia), un repuesto o un accesorio (con cantidad). La escribe solo
     * VentaService (y RepuestosDeReparacionService para los cobros).
     *
     * - `tipo` es GENERADA desde la FK que no es NULL: no puede contradecirla, y
     *   es la columna por la que agrupan los reportes.
     * - `producto_id` UNIQUE: un telefono no puede estar en dos ventas a la vez.
     *   Anular la linea la BORRA, asi que revender sigue funcionando.
     * - Cobro de reparacion: una linea de repuesto con
     *   `producto_reparacion_repuesto_id` cobra una pieza que el tecnico YA monto
     *   (y que ya descontó stock): costo 0, NO mueve stock. El UNIQUE de esa
     *   columna es quien impide de verdad el doble cobro.
     * - `articulo_clave` (P/C/R/A + id) es la clave natural de la linea en la
     *   venta: editar y reintentar choca con el UNIQUE en vez de duplicar la
     *   linea y mover el stock dos veces. El prefijo C deja que el mismo
     *   repuesto aparezca suelto y como cobro en la misma venta.
     *
     * Todas las FK de articulo, la del cobro y la de cabecera van en RESTRICT:
     * MySQL prohibe acciones referenciales sobre columnas de un CHECK o de una
     * columna generada STORED. Y es lo correcto: antes `vrd_reparacion_repuesto_fk`
     * iba en SET NULL, y borrar la pieza de la reparacion convertia el cobro en
     * una venta normal que empezaba a mover stock que nunca debio mover.
     */
    public function up(): void
    {
        Schema::create('ventas_detalles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venta_id');
            $table->unsignedBigInteger('producto_id')->nullable();
            $table->unsignedBigInteger('repuesto_id')->nullable();
            $table->unsignedBigInteger('accesorio_id')->nullable();
            $table->unsignedBigInteger('producto_reparacion_repuesto_id')->nullable();
            // Un accesorio REGALADO con el equipo (productos_regalos): precio 0,
            // costo 0 (ya esta en el costo_total del equipo) y sin mover stock
            // (salio al regalarlo). Lo escribe VentaService con el equipo.
            $table->unsignedBigInteger('producto_regalo_id')->nullable();
            $table->string('tipo', 10)->storedAs(
                "CASE WHEN producto_id IS NOT NULL THEN 'Producto'
                      WHEN repuesto_id IS NOT NULL THEN 'Repuesto'
                      WHEN accesorio_id IS NOT NULL THEN 'Accesorio' END"
            );
            $table->string('articulo_clave', 24)->storedAs(
                "CASE WHEN producto_id IS NOT NULL THEN CONCAT('P', producto_id)
                      WHEN producto_reparacion_repuesto_id IS NOT NULL THEN CONCAT('C', producto_reparacion_repuesto_id)
                      WHEN repuesto_id IS NOT NULL THEN CONCAT('R', repuesto_id)
                      WHEN producto_regalo_id IS NOT NULL THEN CONCAT('G', producto_regalo_id)
                      ELSE CONCAT('A', accesorio_id) END"
            );
            // De donde salio el stock, congelada: anular la linea lo devuelve aqui.
            $table->unsignedBigInteger('sucursal_id')->nullable();
            // Un accesorio o repuesto que se vendio CON este equipo (la funda
            // del telefono): se agrupa bajo el en el detalle y en la nota.
            $table->unsignedBigInteger('producto_asociado_id')->nullable();
            $table->unsignedInteger('cantidad')->default(1);
            $table->decimal('costo', 10, 2)->default(0);
            $table->decimal('precio', 10, 2)->default(0);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('subtotal_costo', 12, 2)->default(0);
            // Solo lineas de equipo.
            $table->unsignedSmallInteger('garantia_meses')->nullable();
            $table->date('garantia_fecha_exp')->nullable();
            // Congelado al vender, para que "Venta externa" se separe en los
            // reportes aunque el equipo se edite despues.
            $table->enum('tipo_venta', ProductoTipoVenta::values())->nullable();
            $table->timestamps();

            $table->foreign('venta_id', 'vd_venta_fk')->references('id')->on('ventas')->restrictOnDelete();
            $table->foreign('producto_id', 'vd_producto_fk')->references('id')->on('productos')->restrictOnDelete();
            $table->foreign('repuesto_id', 'vd_repuesto_fk')->references('id')->on('repuestos')->restrictOnDelete();
            $table->foreign('accesorio_id', 'vd_accesorio_fk')->references('id')->on('accesorios')->restrictOnDelete();
            $table->foreign('producto_reparacion_repuesto_id', 'vd_reparacion_repuesto_fk')
                ->references('id')->on('productos_reparaciones_repuestos')->restrictOnDelete();
            $table->foreign('producto_regalo_id', 'vd_producto_regalo_fk')
                ->references('id')->on('productos_regalos')->restrictOnDelete();
            $table->foreign('sucursal_id', 'vd_sucursal_fk')->references('id')->on('sucursales')->nullOnDelete();
            $table->foreign('producto_asociado_id', 'vd_producto_asociado_fk')->references('id')->on('productos')->restrictOnDelete();

            $table->unique('producto_id', 'ventas_detalles_producto_unico');
            $table->unique('producto_reparacion_repuesto_id', 'vd_reparacion_repuesto_unico');
            $table->unique('producto_regalo_id', 'vd_producto_regalo_unico');
            $table->unique(['venta_id', 'articulo_clave'], 'vd_venta_articulo_unico');
            $table->index('tipo', 'vd_tipo_index');
        });

        DB::statement('ALTER TABLE ventas_detalles
            ADD CONSTRAINT vd_un_articulo CHECK ((producto_id IS NOT NULL) + (repuesto_id IS NOT NULL) + (accesorio_id IS NOT NULL) = 1),
            ADD CONSTRAINT vd_producto_unidad CHECK (producto_id IS NULL OR cantidad = 1),
            ADD CONSTRAINT vd_cantidad_positiva CHECK (cantidad >= 1),
            ADD CONSTRAINT vd_cobro_es_repuesto CHECK (producto_reparacion_repuesto_id IS NULL OR repuesto_id IS NOT NULL),
            ADD CONSTRAINT vd_asociado_es_articulo CHECK (producto_asociado_id IS NULL OR producto_id IS NULL),
            ADD CONSTRAINT vd_regalo_es_accesorio CHECK (producto_regalo_id IS NULL OR (accesorio_id IS NOT NULL AND producto_asociado_id IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_detalles');
    }
};
