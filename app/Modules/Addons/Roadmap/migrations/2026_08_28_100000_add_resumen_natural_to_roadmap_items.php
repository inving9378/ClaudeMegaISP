<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #652 — RESUMEN EN LENGUAJE NATURAL para la bandeja de Irving.
 *
 * POR QUÉ UNA COLUMNA NUEVA Y NO REUSAR `reporte_coloquial`:
 * ese campo se llama coloquial pero NO lo es. `RoadmapItem::generarReporteColoquial()`
 * (línea 569) hace `titulo . '. ' . descripcion` y corta a 40 palabras — sin IA y sin
 * reescribir nada. Es el mismo texto técnico, más largo. Y además `JarvisService:1252`
 * lo usa como PUERTA DE CALIDAD ("falta reporte_coloquial … sin esto Irving no puede
 * revisarlo"): cambiarle el significado rompería esa compuerta sin que nadie lo note.
 *
 * Aditiva y nullable: los items sin resumen caen al título de siempre, así que la
 * bandeja nunca se queda en blanco mientras el backfill avanza.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('roadmap_items', 'resumen_natural')) {
            Schema::table('roadmap_items', function (Blueprint $t) {
                $t->text('resumen_natural')->nullable()->after('reporte_coloquial');
            });
        }

        // Cuándo y con qué modelo se escribió: si mañana se cambia el prompt, se puede
        // saber qué resúmenes son de la versión vieja sin adivinar por la redacción.
        if (! Schema::hasColumn('roadmap_items', 'resumen_natural_at')) {
            Schema::table('roadmap_items', function (Blueprint $t) {
                $t->timestamp('resumen_natural_at')->nullable()->after('resumen_natural');
            });
        }
    }

    public function down(): void
    {
        foreach (['resumen_natural_at', 'resumen_natural'] as $col) {
            if (Schema::hasColumn('roadmap_items', $col)) {
                Schema::table('roadmap_items', function (Blueprint $t) use ($col) {
                    $t->dropColumn($col);
                });
            }
        }
    }
};
