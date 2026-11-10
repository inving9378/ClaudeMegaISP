<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * voip_llamadas.uniqueid deja de ser único.
 *
 * Con una cola, Asterisk escribe una fila CDR por cada agente al que le timbró
 * (el que no contestó → NO ANSWER, el que contestó → ANSWERED), y todas llevan el
 * uniqueid del canal del que llama. El índice único rechazaba la segunda: se
 * perdía justo la fila que dice qué agente contestó. Encontrado el 2026-11-10
 * probando el tablero de llamadas con un agente de prueba (ids saltados en la
 * tabla = inserciones rechazadas).
 *
 * La migración que crea la tabla ya la crea con índice normal (instalaciones
 * nuevas, vía el provisionador). Esta corrige las tablas que ya existen; si no
 * hay central o no hay tabla, no hace nada.
 */
return new class extends Migration
{
    protected $connection = 'asterisk_rt';

    private function indices(): array
    {
        try {
            DB::connection('asterisk_rt')->getPdo();
            if (! Schema::connection('asterisk_rt')->hasTable('voip_llamadas')) {
                return [];
            }

            return collect(DB::connection('asterisk_rt')->select('SHOW INDEX FROM voip_llamadas'))
                ->pluck('Key_name')->unique()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function up(): void
    {
        $indices = $this->indices();
        if (! in_array('voip_llamadas_uniqueid_unique', $indices, true)) {
            return;
        }

        Schema::connection('asterisk_rt')->table('voip_llamadas', function (Blueprint $table) use ($indices) {
            $table->dropUnique('voip_llamadas_uniqueid_unique');
            if (! in_array('voip_llamadas_uniqueid_index', $indices, true)) {
                $table->index('uniqueid');
            }
        });
    }

    public function down(): void
    {
        // No se vuelve a poner único: con filas de varios agentes por llamada,
        // el índice único ya no se puede crear sin borrar registros.
    }
};
