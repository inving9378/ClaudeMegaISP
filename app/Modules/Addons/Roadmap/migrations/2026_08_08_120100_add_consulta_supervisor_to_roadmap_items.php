<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TORRE V2 — CANAL DE CONSULTA TERMINAL → THOMAS. ADITIVA / reversible.
 *
 * El problema que resuelve: hoy una terminal que duda solo tiene UNA salida —
 * `estado_aprobacion = requiere_irving`— así que cualquier titubeo despierta al humano. No existe
 * autoridad intermedia. Estas columnas abren el canal que faltaba: la terminal PREGUNTA y se
 * detiene; Thomas responde con la política fija (config `circuito.thomas`) y el item sigue; solo
 * lo irreversible de alto impacto llega a Irving.
 *
 * No se toca el enum `estado_aprobacion`: una consulta viva es un item `en_progreso` con
 * `consulta_supervisor_at` puesto y `consulta_resuelta_at` nulo. Así ninguna query existente
 * cambia de significado y el rollback es soltar columnas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            // --- pregunta (la escribe la terminal) ---
            if (! Schema::hasColumn('roadmap_items', 'consulta_supervisor')) {
                $table->text('consulta_supervisor')->nullable()->after('claimed_at');
            }
            if (! Schema::hasColumn('roadmap_items', 'consulta_supervisor_sid')) {
                $table->string('consulta_supervisor_sid', 16)->nullable()->after('consulta_supervisor');
            }
            if (! Schema::hasColumn('roadmap_items', 'consulta_supervisor_at')) {
                $table->timestamp('consulta_supervisor_at')->nullable()->after('consulta_supervisor_sid');
            }
            // Opciones que la terminal propone, con su recomendada (mismo contrato de brief que
            // usa el autopilot: [{texto, recomendada, confianza, reversible}]).
            if (! Schema::hasColumn('roadmap_items', 'consulta_opciones')) {
                $table->json('consulta_opciones')->nullable()->after('consulta_supervisor_at');
            }

            // --- respuesta (la escribe Thomas, o Irving si se escaló) ---
            if (! Schema::hasColumn('roadmap_items', 'consulta_respuesta')) {
                $table->text('consulta_respuesta')->nullable()->after('consulta_opciones');
            }
            if (! Schema::hasColumn('roadmap_items', 'consulta_resuelta_at')) {
                $table->timestamp('consulta_resuelta_at')->nullable()->after('consulta_respuesta');
            }
            if (! Schema::hasColumn('roadmap_items', 'consulta_resuelta_por')) {
                $table->string('consulta_resuelta_por', 64)->nullable()->after('consulta_resuelta_at');
            }
        });

        // Índice de la única query caliente: "consultas vivas" (preguntadas y sin responder).
        // Se crea aparte porque las columnas deben existir antes.
        if (! $this->indexExists('roadmap_items', 'ri_consulta_viva_idx')) {
            Schema::table('roadmap_items', function (Blueprint $table) {
                $table->index(['consulta_supervisor_at', 'consulta_resuelta_at'], 'ri_consulta_viva_idx');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('roadmap_items', 'ri_consulta_viva_idx')) {
            Schema::table('roadmap_items', function (Blueprint $table) {
                $table->dropIndex('ri_consulta_viva_idx');
            });
        }

        Schema::table('roadmap_items', function (Blueprint $table) {
            foreach ([
                'consulta_supervisor', 'consulta_supervisor_sid', 'consulta_supervisor_at',
                'consulta_opciones', 'consulta_respuesta', 'consulta_resuelta_at', 'consulta_resuelta_por',
            ] as $col) {
                if (Schema::hasColumn('roadmap_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function indexExists(string $tabla, string $indice): bool
    {
        return collect(\Illuminate\Support\Facades\DB::select("SHOW INDEX FROM `{$tabla}`"))
            ->contains(fn ($fila) => $fila->Key_name === $indice);
    }
};
