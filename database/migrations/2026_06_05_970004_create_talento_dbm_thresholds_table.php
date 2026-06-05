<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_dbm_thresholds', function (Blueprint $table) {
            $table->id();
            // Rango: dbm_min <= valor < dbm_max  (NULL = sin límite en esa dirección)
            $table->decimal('dbm_min', 6, 2)->nullable(); // null = -∞
            $table->decimal('dbm_max', 6, 2)->nullable(); // null = +∞
            // 'sobrepotencia' | 'aviso' | 'excelente' | 'cumplio' | 'baja_senal'
            $table->string('categoria', 40);
            // 'bloquea' | 'alerta' | 'ok'
            $table->string('accion', 20);
            // Mensaje a mostrar al técnico
            $table->string('mensaje', 200);
            $table->boolean('aplica_bono')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_dbm_thresholds');
    }
};
