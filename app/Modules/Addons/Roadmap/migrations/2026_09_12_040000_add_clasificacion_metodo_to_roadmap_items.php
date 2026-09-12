<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #9990875 (PASO 3 de #9990861) — columna aditiva para dejar trazabilidad de CÓMO se asignó
 * `nivel_riesgo` a un item, aplicada primero al backfill masivo de los 400 items ya CERRADOS
 * que llegaron sin nivel_riesgo (metadata histórica, sin impacto en pool/dispatch).
 *
 * Valores libres (string, no ENUM — mismo patrón que `frontera_valvula`/`motivo_espera`):
 *   heuristica | manual | llm
 * NULL = nivel_riesgo asignado por el flujo normal del circuito (triaje/revisor/autopilot),
 * que es el caso de todos los items ya existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'clasificacion_metodo')) {
                $table->string('clasificacion_metodo', 20)->nullable()->after('nivel_riesgo_origen');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (Schema::hasColumn('roadmap_items', 'clasificacion_metodo')) {
                $table->dropColumn('clasificacion_metodo');
            }
        });
    }
};
