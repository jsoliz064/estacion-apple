<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos_reparaciones_repuestos', function (Blueprint $table) {
            $table->id();
            $table->decimal('costo', 10, 2)->default(0);
            $table->integer('cantidad');
            $table->decimal('subtotal_costo', 10, 2)->default(0);
            $table->foreignId('producto_reparacion_id')->constrained('productos_reparaciones')->cascadeOnDelete();
            // RESTRICT: un repuesto montado en una reparacion tiene historia y no
            // se borra (antes cascade se llevaba la pieza en silencio).
            $table->foreignId('repuesto_id')->constrained('repuestos')->restrictOnDelete();
            // Sucursal de la que salio la pieza, congelada en la linea: al
            // quitarla despues el stock vuelve a la sucursal original.
            $table->unsignedBigInteger('sucursal_id')->nullable();
            $table->timestamps();

            $table->foreign('sucursal_id', 'prr_sucursal_foreign')
                ->references('id')->on('sucursales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos_reparaciones_repuestos');
    }
};
