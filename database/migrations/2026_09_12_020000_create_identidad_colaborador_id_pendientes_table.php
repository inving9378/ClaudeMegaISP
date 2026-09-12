<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3b de #9990778 (Identidad unificada, item #9990963) — tabla de auditoría de
 * `seller_id` que la doble escritura NO pudo resolver a `colaborador_id` (sin fila
 * correspondiente en `talento_colaboradores.user_id`). Nunca bloquea el alta/edición
 * que la origina; solo registra el hueco para revisarlo aparte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identidad_colaborador_id_pendientes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->string('tabla');
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tabla', 'seller_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identidad_colaborador_id_pendientes');
    }
};
