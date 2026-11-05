<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UNI-T J1: identidad única de un movimiento del registro de incentivos.
 * Aditiva e inerte por defecto: solo crea el índice con el interruptor
 * `unificacion.interruptores.ledger_identidad_unica` encendido y sin duplicados previos
 * (si los hay, no toca nada y lo deja en el log; se resuelven antes, sin borrar).
 */
return new class extends Migration
{
    private const INDICE = 'tle_identidad_unica';

    public function up(): void
    {
        if (! config('unificacion.interruptores.ledger_identidad_unica')) {
            return;
        }
        if (! Schema::hasTable('talento_ledger_entries') || $this->existe()) {
            return;
        }

        $dups = DB::table('talento_ledger_entries')->whereNotNull('reference_id')
            ->groupBy('colaborador_id', 'concept', 'reference_type', 'reference_id', 'period_start', 'period_end')
            ->havingRaw('COUNT(*) > 1')->count();
        if ($dups > 0) {
            logger()->warning("UNI-T J1: {$dups} grupos duplicados en talento_ledger_entries; índice único NO creado.");
            return;
        }

        Schema::table('talento_ledger_entries', function ($t) {
            $t->unique(['colaborador_id', 'concept', 'reference_type', 'reference_id', 'period_start', 'period_end'], self::INDICE);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('talento_ledger_entries') && $this->existe()) {
            Schema::table('talento_ledger_entries', fn ($t) => $t->dropUnique(self::INDICE));
        }
    }

    private function existe(): bool
    {
        return count(DB::select("SHOW INDEX FROM talento_ledger_entries WHERE Key_name = ?", [self::INDICE])) > 0;
    }
};
