<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una sola cabecera para todo lo que se vende: equipos, repuestos y
     * accesorios, en Bs. Lo vendido vive en ventas_detalles.
     *
     * Antes eran dos documentos (ventas y ventas_repuestos), y cobrar los
     * repuestos de una reparacion junto con el telefono creaba una segunda
     * venta enlazada por venta_id. Ahora todo es una venta, una nota y un
     * cobro (etapa 5: permuta, venta en USD).
     *
     * El `id` es el numero correlativo de la nota.
     *
     * EL COBRO
     * Lo cobrado vive en ventas_pagos (al vender y en cobros posteriores) y lo
     * escribe solo PagoService. Aqui quedan:
     *   pagado    = SUM(ventas_pagos.monto), cacheado por PagoService::sincronizar()
     *   saldo     = total - pagado, GENERADA: no puede contradecir a las dos
     *   pagada_at = cuando el saldo llego a cero (la comision del vendedor se
     *               gana ahi, etapa 7). NULL mientras la venta este a credito.
     */
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->string('clave_idempotencia', 36)->nullable();
            // Cacheados desde las lineas por Venta::recalcularTotales():
            //   subtotal    = SUM(lineas.subtotal)
            //   total       = subtotal - descuento + mano_obra
            //   costo_total = SUM(lineas.subtotal_costo) + mano_obra
            // mano_obra suma al total Y al costo para cancelarse en la ganancia.
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('mano_obra', 10, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('costo_total', 12, 2)->default(0);
            $table->decimal('pagado', 12, 2)->default(0);
            $table->decimal('saldo', 12, 2)->storedAs('total - pagado');
            $table->timestamp('pagada_at')->nullable();
            // Texto congelado de lo que se escribio al vender: la ficha es lo
            // que se muestra (Venta::nombreCliente()), este texto es el archivo.
            $table->string('cliente')->nullable();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->timestamps();

            $table->unique('clave_idempotencia', 'ventas_clave_idem_unico');
            $table->index('created_at', 'ventas_fecha_index');
            // La pantalla de cobranzas filtra por saldo > 0.
            $table->index('saldo', 'ventas_saldo_index');
        });

        // Ni se cobra de mas ni queda un pagado negativo. Lo valida antes
        // PagoService con un mensaje; esto es la garantia.
        DB::statement('ALTER TABLE ventas
            ADD CONSTRAINT ventas_pagado_rango CHECK (pagado >= 0 AND pagado <= total)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
