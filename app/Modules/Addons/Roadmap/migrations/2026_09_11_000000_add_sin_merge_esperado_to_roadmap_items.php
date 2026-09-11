<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #9990738 (Fase 3 de #9990719/#9990730, q2 ya aprobada por Irving) — el gate de cierre
 * (`JarvisService::verificarCierre()`) solo bloqueaba el cierre SIN rama y SIN merge_commit
 * (#9990403). Quedaba sin cubrir el caso intermedio: item CON rama pero SIN merge_commit que
 * intenta cerrar a `completado` — el bloque (1) de `RoadmapItem.php` solo lo parquea para
 * nivel_riesgo==='C'; A/B pasaban de largo. Estos dos campos son el escape valve EXPLÍCITO
 * (mismo patrón que `sin_ui`/`sin_ui_motivo` y `cierre_sin_codigo_motivo`): si el merge lo hace
 * un humano aparte o el item no requiere merge, se marca `sin_merge_esperado=true` + su motivo y
 * el bloqueante nuevo deja pasar el cierre. Aditiva + idempotente; down() las elimina.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'sin_merge_esperado')) {
                $table->boolean('sin_merge_esperado')->default(false);
            }
            if (! Schema::hasColumn('roadmap_items', 'sin_merge_esperado_motivo')) {
                $table->text('sin_merge_esperado_motivo')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (Schema::hasColumn('roadmap_items', 'sin_merge_esperado_motivo')) {
                $table->dropColumn('sin_merge_esperado_motivo');
            }
            if (Schema::hasColumn('roadmap_items', 'sin_merge_esperado')) {
                $table->dropColumn('sin_merge_esperado');
            }
        });
    }
};
