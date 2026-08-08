<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 1 — Validación funcional por Irving.
 * Aditiva e idempotente (guard hasColumn). Nada destructivo en up(); drops solo en down().
 * NO toca el enum estado_aprobacion (para no mutar datos): "Reportar problema" usa el flag
 * booleano `revision_tecnica` + estado_aprobacion=requiere_irving (revisión humana en la bandeja).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'validacion_funcional_requerida')) {
                // Sin ->after(): el orden fisico de columnas es cosmetico en MySQL y
                // atarlo a `esperando_merge_irving` hacia que esta migracion muriera con
                // 1054 en cualquier entorno que aun no tuviera esa columna.
                $table->boolean('validacion_funcional_requerida')->default(false);
            }
            if (! Schema::hasColumn('roadmap_items', 'pendiente_validacion_irving')) {
                $table->boolean('pendiente_validacion_irving')->default(false)->index()->after('validacion_funcional_requerida');
            }
            if (! Schema::hasColumn('roadmap_items', 'validado_por_irving')) {
                $table->boolean('validado_por_irving')->default(false)->after('pendiente_validacion_irving');
            }
            if (! Schema::hasColumn('roadmap_items', 'validado_at')) {
                $table->timestamp('validado_at')->nullable()->after('validado_por_irving');
            }
            if (! Schema::hasColumn('roadmap_items', 'validado_por')) {
                $table->string('validado_por')->nullable()->after('validado_at');
            }
            if (! Schema::hasColumn('roadmap_items', 'comentario_validacion')) {
                $table->text('comentario_validacion')->nullable()->after('validado_por');
            }
            // "equivalente" a requiere_revision_tecnica sin tocar el enum estado_aprobacion.
            if (! Schema::hasColumn('roadmap_items', 'revision_tecnica')) {
                $table->boolean('revision_tecnica')->default(false)->after('comentario_validacion');
            }
            // Contenido de la tarjeta de validación (qué se pidió/hizo/cómo probar/resultado esperado/
            // qué no se tocó/integrado_at). El worker lo llena al integrar un cambio seguro.
            if (! Schema::hasColumn('roadmap_items', 'validacion_brief')) {
                $table->json('validacion_brief')->nullable()->after('revision_tecnica');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            foreach ([
                'validacion_funcional_requerida', 'pendiente_validacion_irving', 'validado_por_irving',
                'validado_at', 'validado_por', 'comentario_validacion', 'revision_tecnica', 'validacion_brief',
            ] as $col) {
                if (Schema::hasColumn('roadmap_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
