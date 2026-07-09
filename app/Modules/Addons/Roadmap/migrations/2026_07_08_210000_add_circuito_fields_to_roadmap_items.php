<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CIRCUITO DE MEJORA CONTINUA — Parte 1.1
 *
 * Campos aditivos sobre roadmap_items para el flujo de aprobación externa:
 *  - modulo             : módulo del sistema al que pertenece el hallazgo (auditoría)
 *  - nivel_riesgo       : A (seguro/aditivo), B (confirma Irving), C (decisión de diseño de Irving)
 *  - estado_aprobacion  : ciclo de revisión de Claude Cowork
 *  - comentarios_claude : notas que deja Claude Cowork al revisar
 *  - revisado_at        : timestamp de la última revisión externa
 *  - aprobado_por       : quién dejó el veredicto (p.ej. "claude-cowork")
 *
 * NOTA: prompt_para_claude REUSA la columna existente `prompt` (ver RoadmapItem).
 * Idempotente: cada columna se agrega solo si falta. down() las retira sin tocar el resto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'modulo')) {
                $table->string('modulo')->nullable()->after('title');
                $table->index('modulo');
            }
            if (! Schema::hasColumn('roadmap_items', 'nivel_riesgo')) {
                $table->enum('nivel_riesgo', ['A', 'B', 'C'])->nullable()->after('priority');
                $table->index('nivel_riesgo');
            }
            if (! Schema::hasColumn('roadmap_items', 'estado_aprobacion')) {
                $table->enum('estado_aprobacion', [
                    'pendiente_revision',
                    'aprobado_claude',
                    'requiere_irving',
                    'rechazado',
                    'en_progreso',
                    'completado',
                ])->default('pendiente_revision')->after('nivel_riesgo');
                $table->index('estado_aprobacion');
            }
            if (! Schema::hasColumn('roadmap_items', 'comentarios_claude')) {
                $table->text('comentarios_claude')->nullable()->after('prompt');
            }
            if (! Schema::hasColumn('roadmap_items', 'revisado_at')) {
                $table->timestamp('revisado_at')->nullable()->after('completed_at');
            }
            if (! Schema::hasColumn('roadmap_items', 'aprobado_por')) {
                $table->string('aprobado_por')->nullable()->after('revisado_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            foreach (['modulo', 'nivel_riesgo', 'estado_aprobacion', 'comentarios_claude', 'revisado_at', 'aprobado_por'] as $col) {
                if (Schema::hasColumn('roadmap_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
