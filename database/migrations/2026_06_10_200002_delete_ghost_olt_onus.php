<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Elimina las filas fantasma de olt_onus creadas antes de GR-3c por el
     * upsert (sn, olt_id): ids 219 y 239, misma SN que filas legítimas pero
     * con olt_id erróneo (2 en lugar del OLT real de la ONU).
     *
     * Idempotente: si las filas ya no existen, no hace nada.
     */
    public function up(): void
    {
        $deleted = DB::table('olt_onus')
            ->whereIn('id', [219, 239])
            ->delete();

        Log::info("[GR-3c SP2] ghost olt_onus eliminados: {$deleted} fila(s) (ids 219, 239).");
    }

    public function down(): void
    {
        // No se recrea: estos registros eran duplicados incorrectos.
        // Idempotente en ambas direcciones.
    }
};
