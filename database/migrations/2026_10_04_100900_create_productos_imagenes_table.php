<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos_imagenes', function (Blueprint $table) {
            $table->id();
            // Archivo en el disco public, no un data URL base64: el base64
            // inflaba la base y los respaldos, viajaba en cada peticion de
            // Livewire y el navegador no podia cachearlo.
            // Relativa al disco public: productos/{producto_id}/{uuid}.jpg
            $table->string('ruta');
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos_imagenes');
    }
};
