<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TORRE V2 — HISTORIAL DE REPORTES POR ITEM. ADITIVA / reversible.
 *
 * Hasta ahora todo reporte de una terminal se escribía PISANDO o concatenando a mano sobre
 * `roadmap_items.comentarios_claude` / `reporte_tecnico` / `reporte_coloquial`. Con seis
 * terminales, Thomas y la vía externa escribiendo sobre el mismo item, esa columna es una
 * pizarra compartida: el último en escribir gana y se pierde el rastro de quién decidió qué.
 *
 * Aquí cada reporte es una FILA propia (append-only, nunca se actualiza ni se borra):
 *  - `autor`  → 'wt-3' | 'thomas' | 'claude-cowork' | 'irving' | 'claude-code'
 *  - `tipo`   → qué clase de evento es (ver TIPOS en RoadmapItemReport)
 *  - `meta`   → JSON libre: la decisión tomada, la opción elegida, el commit, el motivo…
 *
 * Las columnas viejas NO se tocan: siguen sirviendo de resumen para la UI actual. Este historial
 * es la fuente completa detrás de ese resumen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('roadmap_item_reports')) {
            return; // re-ejecutable
        }

        Schema::create('roadmap_item_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('roadmap_item_id');

            // Quién reporta. Texto libre acotado (no FK): los autores son terminales (wt-K),
            // procesos (thomas, merge-runner) y vías externas, no filas de `users`.
            $table->string('autor', 64);

            // Clase de evento. Se valida en la aplicación (RoadmapItemReport::TIPOS) y no como
            // enum de MySQL, para poder sumar tipos sin ALTER sobre una tabla que crece.
            $table->string('tipo', 24)->default('nota');

            // Titular legible de una línea (lo que se pinta en la Torre sin abrir el detalle).
            $table->string('resumen', 500);

            // Cuerpo completo opcional (el reporte largo de la terminal).
            $table->text('cuerpo')->nullable();

            // Datos estructurados del evento: opción elegida, confianza, commit, archivos…
            $table->json('meta')->nullable();

            // Append-only: solo hay fecha de creación, jamás se actualiza una fila.
            $table->timestamp('created_at')->useCurrent();

            // El acceso real siempre es "los reportes de este item, del más nuevo al más viejo".
            $table->index(['roadmap_item_id', 'created_at'], 'rir_item_fecha_idx');

            $table->foreign('roadmap_item_id')
                ->references('id')->on('roadmap_items')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_item_reports');
    }
};
