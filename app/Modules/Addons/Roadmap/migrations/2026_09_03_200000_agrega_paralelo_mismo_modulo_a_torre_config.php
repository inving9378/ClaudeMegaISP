<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Circuito CC #911 Fase 5 (cont.) — item #9990005. Expone `paralelo_mismo_modulo` (hasta hoy
 * solo en `config('circuito.paralelo_mismo_modulo', 1)`) en `torre_config`, mismo patrón que
 * `autopilot_max_nivel`: NULL = lo gobierna `config/circuito.php` (estado de fábrica, sin cambio
 * de comportamiento al migrar); un entero 1-6 puesto desde la pantalla manda.
 *
 * La lectura real (`RoadmapCircuitoService::ejecutablesParalelo()`) se re-cablea aparte, en el
 * mismo item, para resolver vía `TorreConfig::paraleloMismoModulo()` — aquí solo se siembra la
 * columna, aditiva y sin efecto hasta que algo la lea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torre_config', function (Blueprint $table) {
            if (! Schema::hasColumn('torre_config', 'paralelo_mismo_modulo')) {
                $table->unsignedTinyInteger('paralelo_mismo_modulo')->nullable()->after('auditor_slots_libres_min');
            }
        });
    }

    public function down(): void
    {
        Schema::table('torre_config', function (Blueprint $table) {
            if (Schema::hasColumn('torre_config', 'paralelo_mismo_modulo')) {
                $table->dropColumn('paralelo_mismo_modulo');
            }
        });
    }
};
