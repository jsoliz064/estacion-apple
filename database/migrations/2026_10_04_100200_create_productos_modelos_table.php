<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos_modelos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->foreignId('producto_categoria_id')->constrained('productos_categorias')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos_modelos');
    }
};
