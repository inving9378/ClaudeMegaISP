<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Torre 24/7 Pieza 5a-ii (item #981) — nuevo parámetro configurable: cuántos slots libres mínimos
 * necesita ver el auditor antes de disparar. Mismo patrón que `auditor_max_por_corrida` /
 * `auditor_cooldown_min` (migración `2026_08_19_100000_...`).
 *
 * Aditiva. Default conservador = 2 (lo que ya usa hoy `TorreAutomationPolicy`/el resto del
 * auditor vía `config('circuito.auditor.slots_libres_min_disparo')`), así que sembrar la columna
 * no cambia el comportamiento actual hasta que Irving la toque desde el panel.
 *
 * Depende del item hermano "condición de disparo" (#980) para que algo LEA este valor; por sí
 * sola no rompe nada — es sólo el sustrato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torre_config', function (Blueprint $table) {
            if (! Schema::hasColumn('torre_config', 'auditor_slots_libres_min')) {
                $table->unsignedTinyInteger('auditor_slots_libres_min')->default(2)->after('auditor_cooldown_min');
            }
        });

        DB::table('torre_config')->update([
            'auditor_slots_libres_min' => (int) config('circuito.auditor.slots_libres_min_disparo', 2),
        ]);
    }

    public function down(): void
    {
        Schema::table('torre_config', function (Blueprint $table) {
            if (Schema::hasColumn('torre_config', 'auditor_slots_libres_min')) {
                $table->dropColumn('auditor_slots_libres_min');
            }
        });
    }
};
