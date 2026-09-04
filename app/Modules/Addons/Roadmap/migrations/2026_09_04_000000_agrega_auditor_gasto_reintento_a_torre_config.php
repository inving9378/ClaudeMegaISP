<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Freno de sequía N2 (#891) — Fase 3b-i (item #9990016): sustrato de `torre_config` para el
 * half-open que la Fase 3a (#925/#9990011) ya implementó leyendo de `config/circuito.php`.
 * Mismo patrón que `auditor_slots_libres_min` (migración `2026_09_03_190000_...`): columna con
 * default de fábrica + backfill desde la config vigente, para que sembrarla no cambie el
 * comportamiento actual hasta que Irving la mueva desde el panel.
 *
 * Aditiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torre_config', function (Blueprint $table) {
            if (! Schema::hasColumn('torre_config', 'auditor_gasto_reintento_min')) {
                $table->unsignedSmallInteger('auditor_gasto_reintento_min')->default(30)->after('paralelo_mismo_modulo');
            }
            if (! Schema::hasColumn('torre_config', 'auditor_gasto_reintento_activo')) {
                $table->boolean('auditor_gasto_reintento_activo')->default(true)->after('auditor_gasto_reintento_min');
            }
        });

        DB::table('torre_config')->update([
            'auditor_gasto_reintento_min' => (int) config('circuito.auditor.sequia.gasto_reintento_min', 30),
            'auditor_gasto_reintento_activo' => (bool) config('circuito.auditor.sequia.gasto_reintento_activo', true),
        ]);
    }

    public function down(): void
    {
        Schema::table('torre_config', function (Blueprint $table) {
            if (Schema::hasColumn('torre_config', 'auditor_gasto_reintento_activo')) {
                $table->dropColumn('auditor_gasto_reintento_activo');
            }
            if (Schema::hasColumn('torre_config', 'auditor_gasto_reintento_min')) {
                $table->dropColumn('auditor_gasto_reintento_min');
            }
        });
    }
};
