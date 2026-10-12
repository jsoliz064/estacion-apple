<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tecnicos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            // % de la mano de obra cobrada que se lleva el tecnico (50/50 por
            // defecto: los repuestos los pone el negocio).
            $table->decimal('comision_porcentaje', 5, 2)->default(50);
            // Su usuario del sistema, si tiene: con el ve sus comisiones en
            // «Mis comisiones». Uno a uno.
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tecnicos');
    }
};
