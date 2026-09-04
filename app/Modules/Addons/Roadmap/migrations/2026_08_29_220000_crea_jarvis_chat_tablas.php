<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #806 (Jarvis Parte 3b) — persistencia del chat donde Irving conversa el brief de una
 * sugerencia detectada por Jarvis (#805, `circuito:jarvis-sugerir`) antes de encolarla como
 * item de la Hoja de Ruta. Decisión de Irving (q2 del brief aprobado): tabla dedicada — no JSON
 * suelto en la sugerencia ni efímero en sesión — para que el hilo sea auditable y retomable.
 *
 * `jarvis_conversaciones`: una fila por sugerencia sobre la que se abrió hilo. Las sugerencias
 * de `JarvisSugerenciasService::detectar()` no tienen id propio (se recalculan cada corrida), así
 * que se identifican por `sugerencia_clave` (hash estable de categoría+texto).
 * `jarvis_mensajes`: el hilo de esa conversación, `rol` 'irving'|'jarvis'.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jarvis_conversaciones')) {
            Schema::create('jarvis_conversaciones', function (Blueprint $table) {
                $table->id();
                $table->string('sugerencia_clave', 40)->unique();
                $table->string('categoria', 60)->nullable();
                $table->text('texto_sugerencia')->nullable();
                $table->json('citas')->nullable();
                // Se llena cuando el hilo se convierte en item vía el botón "Generar item del
                // roadmap" (q4 del brief). Sin FK dura a propósito: mismo estilo ligero que el
                // resto de las tablas de apoyo del módulo (ver vigilante_discrepancias).
                $table->unsignedBigInteger('item_id')->nullable();
                $table->string('estado', 20)->default('abierta'); // abierta|convertida
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index('item_id');
                $table->index('estado');
            });
        }

        if (! Schema::hasTable('jarvis_mensajes')) {
            Schema::create('jarvis_mensajes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('jarvis_conversacion_id');
                $table->string('rol', 10); // irving|jarvis
                $table->text('contenido');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index('jarvis_conversacion_id');
                $table->foreign('jarvis_conversacion_id')
                    ->references('id')->on('jarvis_conversaciones')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jarvis_mensajes');
        Schema::dropIfExists('jarvis_conversaciones');
    }
};
