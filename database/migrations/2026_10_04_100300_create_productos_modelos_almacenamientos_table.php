<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sin timestamps: el modelo declara $timestamps = false.
        Schema::create('productos_modelos_almacenamientos', function (Blueprint $table) {
            $table->id();
            $table->string('almacenamiento');
            $table->decimal('precio', 10, 2)->default(0);
            $table->decimal('costo', 10, 2)->default(0);
            $table->decimal('precio_cliente', 10, 2)->default(0);
            $table->foreignId('producto_modelo_id')->constrained('productos_modelos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos_modelos_almacenamientos');
    }
};
