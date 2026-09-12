<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CIRC-02b PASO 1 — TABLA DE RESPUESTAS DE UN ITEM. ADITIVA / reversible.
 *
 * Hoy el comentario de Irving sobre un item `requiere_irving` se escribe donde sea (Torre,
 * comentarios_claude, un mensaje aparte) y no hay un lugar único que una terminal pueda leer
 * para saber "esto ya lo respondieron, tómalo y sigue". Esta tabla es esa bandeja: cada fila es
 * UNA respuesta a UN item, con su canal de origen y si ya fue consumida.
 *
 * `item_id` lleva FK con cascadeOnDelete (igual que roadmap_item_reports): si el item se borra,
 * sus respuestas no tienen sentido por sí solas y deben irse con él.
 * `canal` es string libre (no enum de MySQL) a propósito: el resto del módulo sufre con ALTER
 * sobre enums cada vez que se suma un valor; valores esperados hoy: torre|api|consola.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('roadmap_item_respuestas')) {
            return; // re-ejecutable
        }

        Schema::create('roadmap_item_respuestas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');

            $table->string('autor', 64);

            // torre|api|consola — string simple, no enum de MySQL (ver nota arriba).
            $table->string('canal', 24)->default('torre');

            $table->text('cuerpo');

            // Si la respuesta autoriza a la terminal a ejecutar directo (true) o solo deja
            // constancia/aclaración sin destrabar nada (false).
            $table->boolean('ejecutar')->default(true);

            $table->timestamp('consumida_at')->nullable();
            $table->string('consumida_por', 64)->nullable();

            $table->timestamps();

            $table->index(['item_id', 'consumida_at'], 'rir_item_consumida_idx');

            $table->foreign('item_id')
                ->references('id')->on('roadmap_items')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_item_respuestas');
    }
};
