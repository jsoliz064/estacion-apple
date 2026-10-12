<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una sola cabecera para todo lo que se compra: equipos, repuestos y
     * accesorios. Lo que se compro vive en compras_detalles.
     *
     * Antes eran dos documentos: `compras` (el lote de telefonos, con proveedor
     * pero sin usuario ni sucursal) y `compras_repuestos` (sin proveedor). La
     * etapa 6 (estado, reclamos, cuentas por pagar) necesita UN documento con
     * proveedor para todo.
     */
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            // Idempotencia: reintentar un guardado no crea una segunda compra.
            // El nombre del indice debe contener "clave_idem": es lo que busca
            // GuardadoIdempotenteTrait::esClaveDuplicada().
            $table->string('clave_idempotencia', 36)->nullable();
            $table->date('fecha');
            // Total cacheado = SUM(compras_detalles.subtotal). Solo lo escribe
            // Compra::recalcularTotal().
            $table->decimal('total', 12, 2)->default(0);
            // Cuentas por pagar (como ventas): lo pagado al proveedor vive en
            // compras_pagos y lo cachea PagoProveedorService. `saldo` es GENERADA.
            $table->decimal('pagado', 12, 2)->default(0);
            $table->decimal('saldo', 12, 2)->storedAs('total - pagado');
            $table->timestamp('pagada_at')->nullable();
            // NULL = Borrador: la cabecera existe y se va cargando, pero nada
            // entro al stock ni se puede vender. CompraService::finalizar() la
            // fija y recien ahi el stock entra y los equipos salen de EnCompra.
            $table->timestamp('finalizada_at')->nullable();
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Sucursal a la que entra lo comprado (cada linea la congela).
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->timestamps();

            $table->unique('clave_idempotencia', 'compras_clave_idem_unico');
            $table->index('fecha', 'compras_fecha_index');
            $table->index('saldo', 'compras_saldo_index');
            $table->index('finalizada_at', 'compras_finalizada_index');
        });

        DB::statement('ALTER TABLE compras ADD CONSTRAINT compras_pagado_rango CHECK (pagado >= 0 AND pagado <= total)');
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
