<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #1110 — Jarvis de la Torre, Fase 2: propuestas de items hechas desde la conversación. El modelo
 * solo PROPONE; esta tabla guarda el borrador (cada versión), su huella y, al crearse, los items
 * resultantes. Es también el historial: quién lo pidió, cuándo, de qué conversación.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jarvis_propuestas')) {
            return;
        }
        Schema::create('jarvis_propuestas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('conversacion_id')->index();
            $table->unsignedBigInteger('mensaje_id')->nullable();
            $table->json('borrador');
            $table->string('pregunta', 500)->nullable();
            $table->char('huella', 64);
            $table->unsignedInteger('version')->default(1);
            $table->string('estado', 20)->default('propuesta')->index();   // propuesta|editada|creada|descartada
            $table->json('item_ids')->nullable();                          // [{indice,id,creado_at}]
            $table->json('historial')->nullable();                         // versiones anteriores del borrador
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jarvis_propuestas');
    }
};
