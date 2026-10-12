<?php

use App\Enums\ReparacionTipo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos_reparaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('tecnico_id')->nullable()->constrained('tecnicos')->nullOnDelete();
            $table->decimal('costo', 10, 2)->default(0);
            $table->decimal('costo_repuestos', 10, 2)->default(0);
            $table->decimal('costo_total', 10, 2)->default(0);
            $table->decimal('cobro_cliente', 10, 2)->default(0);
            $table->text('repuestos_tecnico')->nullable();
            $table->text('repuestos_propios')->nullable();
            $table->text('repuestos_devolver')->nullable();
            $table->enum('estado', ['Pendiente', 'Terminado'])->default('Pendiente');
            $table->enum('tipo', array_map(fn($c) => $c->value, ReparacionTipo::cases()))
                ->default(ReparacionTipo::Normal->value);
            $table->date('fecha_entrega')->nullable();
            $table->date('fecha_recogida')->nullable();
            $table->boolean('garantia_tecnico')->default(false);
            // Venta de la garantia que origino la reparacion.
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->timestamps();

            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos_reparaciones');
    }
};
