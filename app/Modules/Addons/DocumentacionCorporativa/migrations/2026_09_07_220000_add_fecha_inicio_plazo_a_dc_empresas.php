<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DC plazo 180d hábiles — Fase 1 (item roadmap #9990573, decisión Irving #9990550 q1/q4).
 *
 * `fecha_inicio_plazo` = fecha de EMISIÓN/EXPEDICIÓN del oficio de la mesa
 * directiva que activa el plazo maestro de 180 días hábiles del expediente. Es
 * un dato de un documento físico externo — no existe hoy ninguna fuente en el
 * sistema de la que derivarlo, por eso nace `nullable` sin backfill (NO se
 * inventa desde `created_at`; sería falsificar un dato legal). Se captura a
 * mano por empresa en una fase posterior (UI de captura, fuera de alcance
 * aquí). El cálculo de "180 hábiles" tampoco vive en esta fase (ver Fase 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dc_empresas', 'fecha_inicio_plazo')) {
            Schema::table('dc_empresas', function (Blueprint $table) {
                $table->date('fecha_inicio_plazo')->nullable()->default(null)->after('fecha_constitucion');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dc_empresas', 'fecha_inicio_plazo')) {
            Schema::table('dc_empresas', function (Blueprint $table) {
                $table->dropColumn('fecha_inicio_plazo');
            });
        }
    }
};
