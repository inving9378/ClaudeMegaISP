<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JARVIS — memoria persistente del asistente flotante (el del robotsito, IaChatFloat.vue).
 *
 * - asistente_conversaciones: un hilo continuo por usuario. `resumen` guarda lo que el
 *   autocompactado ya resumió; los mensajes viejos se marcan `compactado` (no se borran).
 * - asistente_mensajes: cada turno (usuario, JARVIS y resultado de herramienta).
 * - asistente_recuerdos: memoria larga por usuario (preferencias/contexto, nunca datos que cambian).
 * - asistente_acciones: cambios que JARVIS preparó y esperan el "sí" del usuario.
 *
 * ADITIVA e idempotente (guards hasTable). Sin relación con las tablas jarvis_* de la Torre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('asistente_conversaciones')) {
            Schema::create('asistente_conversaciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('titulo', 255)->nullable();
                $table->longText('resumen')->nullable();
                $table->unsignedInteger('compactaciones')->default(0);
                $table->unsignedInteger('tokens_ultima_vuelta')->nullable();
                $table->boolean('activa')->default(true)->index();
                $table->timestamp('ultimo_mensaje_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('asistente_mensajes')) {
            Schema::create('asistente_mensajes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversacion_id')->constrained('asistente_conversaciones')->cascadeOnDelete();
                $table->string('rol', 20); // user | assistant | herramienta
                $table->longText('contenido')->nullable();
                $table->json('llamadas')->nullable();
                $table->string('herramienta_llamada_id', 120)->nullable();
                $table->string('herramienta', 80)->nullable();
                $table->boolean('compactado')->default(false);
                $table->unsignedInteger('tokens_input')->nullable();
                $table->unsignedInteger('tokens_output')->nullable();
                $table->string('pantalla', 255)->nullable();
                $table->timestamps();
                $table->index(['conversacion_id', 'compactado']);
            });
        }

        if (!Schema::hasTable('asistente_recuerdos')) {
            Schema::create('asistente_recuerdos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->text('contenido');
                $table->string('origen', 20)->default('auto'); // auto | usuario
                $table->unsignedBigInteger('conversacion_id')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('asistente_acciones')) {
            Schema::create('asistente_acciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('conversacion_id')->nullable()->index();
                $table->string('herramienta', 80);
                $table->json('argumentos')->nullable();
                $table->text('resumen');
                $table->string('estado', 20)->default('pendiente')->index(); // pendiente | ejecutada | cancelada | expirada | fallida
                $table->json('resultado')->nullable();
                $table->timestamp('expira_at')->nullable();
                $table->timestamp('resuelta_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asistente_acciones');
        Schema::dropIfExists('asistente_recuerdos');
        Schema::dropIfExists('asistente_mensajes');
        Schema::dropIfExists('asistente_conversaciones');
    }
};
