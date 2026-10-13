<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Codigos de barras propios para pegar en la mercaderia ANTES de saber a
     * que producto van: se imprimen, se pegan, y al cargar el equipo o el
     * articulo se escanean a su UPC o SKU. Ver CodigoBarrasService.
     *
     * Que un codigo este asignado no se guarda: se deriva de si aparece como
     * upc o sku en productos, repuestos o accesorios (CodigoBarras::duenos()).
     * Guardarlo seria un segundo escritor que mantener sincronizado.
     */
    public function up(): void
    {
        Schema::create('codigos_lotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cantidad');
            // Stickers por codigo: 20 cargadores iguales son 1 codigo x 20.
            $table->unsignedSmallInteger('copias')->default(1);
            $table->string('nota')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('codigos_barras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained('codigos_lotes')->restrictOnDelete();
            // El numero del que sale el codigo. Se lee bloqueado (MAX + 1) al
            // generar; el UNIQUE es la garantia de verdad si dos lotes chocan.
            $table->unsignedBigInteger('secuencia');
            // EAN-13 de uso interno: '20' + secuencia (10) + verificador.
            $table->char('codigo', 13);
            $table->timestamps();

            $table->unique('secuencia', 'cb_secuencia_unica');
            $table->unique('codigo', 'cb_codigo_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codigos_barras');
        Schema::dropIfExists('codigos_lotes');
    }
};
