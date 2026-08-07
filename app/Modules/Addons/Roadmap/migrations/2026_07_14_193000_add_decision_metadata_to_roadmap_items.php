<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 1 — METADATA ESTRUCTURADA DE DECISIÓN (circuito).
 *
 * Campos ADITIVOS sobre roadmap_items para que el circuito distinga sin ambigüedad:
 * pendiente · resuelta · espera merge manual · sesión supervisada · bloqueado por bucle ·
 * revisión técnica · fuera del pool automático. No reemplaza `opcion_elegida` ni `comentarios_claude`
 * ni el `log` histórico (siguen siendo la fuente legacy). Todo nullable o con default seguro → un item
 * legacy (todos los campos nuevos vacíos/false) se comporta EXACTAMENTE como hoy.
 *
 * Idempotente (cada columna se agrega solo si falta). down() retira solo estas columnas, sin tocar el
 * resto del esquema ni ningún dato. NO ejecuta DML: no modifica filas existentes.
 */
return new class extends Migration
{
    /** Columnas nuevas (orden estable para up/down y para la verificación). */
    private const COLS = [
        'decision_resuelta',
        'decision_resumen',
        'decision_fuente',
        'decision_fecha',
        'alcance_autorizado',
        'fuera_de_alcance',
        'siguiente_accion',
        'requiere_sesion_supervisada',
        'excluir_pool_automatico',
        'bloqueado_por_bucle',
        'motivo_bloqueo',
        'escalaciones_fingerprint',
        'esperando_merge_irving',
    ];

    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            // Núcleo de la decisión persistida.
            if (! Schema::hasColumn('roadmap_items', 'decision_resuelta')) {
                $table->boolean('decision_resuelta')->default(false)->after('opcion_elegida');
                $table->index('decision_resuelta');
            }
            if (! Schema::hasColumn('roadmap_items', 'decision_resumen')) {
                $table->text('decision_resumen')->nullable()->after('decision_resuelta');
            }
            // Origen de la decisión: irving | revisor | claude | sistema. String libre a propósito
            // (no enum) para no bloquear futuros orígenes ni requerir migración para agregarlos.
            if (! Schema::hasColumn('roadmap_items', 'decision_fuente')) {
                $table->string('decision_fuente', 32)->nullable()->after('decision_resumen');
            }
            if (! Schema::hasColumn('roadmap_items', 'decision_fecha')) {
                $table->timestamp('decision_fecha')->nullable()->after('decision_fuente');
            }
            if (! Schema::hasColumn('roadmap_items', 'alcance_autorizado')) {
                $table->json('alcance_autorizado')->nullable()->after('decision_fecha');
            }
            if (! Schema::hasColumn('roadmap_items', 'fuera_de_alcance')) {
                $table->json('fuera_de_alcance')->nullable()->after('alcance_autorizado');
            }
            // Acción siguiente sugerida: mergear|sesion|sub_items|revision_tecnica|archivar|ejecutar|ninguna.
            // String libre por la misma razón que decision_fuente (validación de valores vive en el modelo).
            if (! Schema::hasColumn('roadmap_items', 'siguiente_accion')) {
                $table->string('siguiente_accion', 32)->nullable()->after('fuera_de_alcance');
            }

            // Banderas de ruteo / exclusión del pool automático.
            if (! Schema::hasColumn('roadmap_items', 'requiere_sesion_supervisada')) {
                $table->boolean('requiere_sesion_supervisada')->default(false)->after('siguiente_accion');
                $table->index('requiere_sesion_supervisada');
            }
            if (! Schema::hasColumn('roadmap_items', 'excluir_pool_automatico')) {
                $table->boolean('excluir_pool_automatico')->default(false)->after('requiere_sesion_supervisada');
                $table->index('excluir_pool_automatico');
            }
            if (! Schema::hasColumn('roadmap_items', 'bloqueado_por_bucle')) {
                $table->boolean('bloqueado_por_bucle')->default(false)->after('excluir_pool_automatico');
                $table->index('bloqueado_por_bucle');
            }
            if (! Schema::hasColumn('roadmap_items', 'motivo_bloqueo')) {
                $table->text('motivo_bloqueo')->nullable()->after('bloqueado_por_bucle');
            }
            // Huella semántica de escalaciones para el detector anti-bucle (se puebla en Fase 4).
            if (! Schema::hasColumn('roadmap_items', 'escalaciones_fingerprint')) {
                $table->json('escalaciones_fingerprint')->nullable()->after('motivo_bloqueo');
            }
            // Espera un merge MANUAL de Irving (Integración), no del pool ni del MergeRunner automático.
            if (! Schema::hasColumn('roadmap_items', 'esperando_merge_irving')) {
                $table->boolean('esperando_merge_irving')->default(false)->after('escalaciones_fingerprint');
                $table->index('esperando_merge_irving');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            foreach (self::COLS as $col) {
                if (Schema::hasColumn('roadmap_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
