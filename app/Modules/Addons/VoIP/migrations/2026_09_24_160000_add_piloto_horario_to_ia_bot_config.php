<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MegaVoz Fase 6 (conectar el motor a la cola real) — el kill-switch general
 * ya existía (`enabled`, default false, sin tocar). Estos dos campos deciden
 * CUÁNTO se expone: `piloto_porcentaje` (0-100, default 0 — ni con enabled=true
 * atiende nada hasta que alguien suba el número) y la ventana de horario de
 * oficina en que el piloto corre (fuera de esa ventana, cae al contestador de
 * relleno de siempre aunque el piloto esté prendido).
 *
 * ADITIVA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ia_bot_config', function (Blueprint $table) {
            $table->unsignedTinyInteger('piloto_porcentaje')->default(0)->after('enabled');
            $table->time('horario_inicio')->default('09:00:00')->after('piloto_porcentaje');
            $table->time('horario_fin')->default('20:00:00')->after('horario_inicio');
        });
    }

    public function down(): void
    {
        Schema::table('ia_bot_config', function (Blueprint $table) {
            $table->dropColumn(['piloto_porcentaje', 'horario_inicio', 'horario_fin']);
        });
    }
};
