<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Contexto de lectura para Irving. No alimenta decisiones de trading automáticas en Fase 1.
        Schema::create('inv_market_notes', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('titulo', 200);
            $table->text('resumen')->nullable();
            $table->string('fuente', 120)->nullable();
            $table->string('url')->nullable();
            $table->enum('sentimiento', ['positivo', 'neutral', 'negativo'])->nullable();
            $table->json('assets_relacionados')->nullable();
            $table->enum('generado_por', ['ia', 'manual'])->default('manual');
            $table->timestamps();

            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_market_notes');
    }
};
