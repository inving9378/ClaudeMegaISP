<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Esta migración es de febrero pero olt_smartolt_config (que ambos comandos
        // consultan vía OltSmartoltConfig::current()) la crea una migración de junio
        // (2026_06_13_000001) — en un migrate desde cero aún no existe. Guard try/catch
        // idéntico al que ya usan las migraciones hermanas de este mismo bloque OLT
        // (2026_01_09_000622, 2026_03_07_050150, 2026_03_19_071941, 2026_03_20_092657).
        try {
            Artisan::call('smartolt:sync-inventory');
            Artisan::call('smartolt:sync-critical');
        } catch (\Throwable $th) {
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
