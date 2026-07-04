<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 3 — Pivote función↔línea.
 *
 * Qué funciones atiende cada línea de WhatsApp. UNIQUE(instance_id, function_id) evita
 * duplicar la misma función en la misma línea. La exclusividad (una función exclusiva en
 * una sola línea) NO se puede expresar como unique condicional en MySQL → se enforza en
 * el backend (WhatsAppFunctionService + observers). Los FK cascadeOnDelete son el backstop
 * de BD para hard-deletes; el guard de "no dejar función huérfana" corre ANTES, en el
 * observer de WhatsAppInstance::deleting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_instance_functions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('whatsapp_instances')->cascadeOnDelete();
            $table->foreignId('function_id')->constrained('whatsapp_functions')->cascadeOnDelete();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();

            $table->unique(['instance_id', 'function_id']);
            // foreignId()->constrained() ya indexa cada FK; el unique cubre (instance_id,*).
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_instance_functions');
    }
};
