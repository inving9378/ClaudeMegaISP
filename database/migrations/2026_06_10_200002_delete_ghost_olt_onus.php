<?php

use App\Console\Commands\Olts\DedupeOnus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Deduplica olt_onus por unique_external_id usando criterio de calidad,
     * portable a cualquier entorno (dev / staging / producción).
     *
     * Criterio ganador: Online > con client_id > last_synced_at más reciente > id mayor.
     * Losers se respaldan en olt_onus_dupes_backup antes de eliminarse.
     *
     * Idempotente: si no hay duplicados no hace nada.
     *
     * En dev esta migración ya está registrada (corrió la versión con ids fijos)
     * y no volverá a ejecutarse. El cambio aplica a producción y cualquier
     * entorno fresco donde aún no corrió.
     */
    public function up(): void
    {
        $command = new DedupeOnus();
        $groups  = $command->findDuplicateGroups();

        if (empty($groups)) {
            Log::info('[GR-3c SP2] dedupe olt_onus: sin duplicados, nada que hacer.');
            return;
        }

        $toDelete = [];

        foreach ($groups as $rows) {
            $winner = $command->pickWinner($rows);
            foreach ($rows as $row) {
                if ($row->id !== $winner->id) {
                    $toDelete[] = $row->id;
                }
            }
        }

        if (empty($toDelete)) {
            Log::info('[GR-3c SP2] dedupe olt_onus: sin losers, nada que borrar.');
            return;
        }

        $command->ensureBackupTable();

        DB::transaction(function () use ($toDelete) {
            DB::statement(
                'INSERT INTO olt_onus_dupes_backup SELECT *, NOW() AS backed_up_at FROM olt_onus WHERE id IN (' . implode(',', $toDelete) . ')'
            );

            $deleted = DB::table('olt_onus')->whereIn('id', $toDelete)->delete();

            Log::info(sprintf(
                '[GR-3c SP2] dedupe olt_onus: %d fila(s) retiradas por criterio, ids [%s].',
                $deleted,
                implode(',', $toDelete)
            ));
        });
    }

    public function down(): void
    {
        // El dedupe no se revierte: los registros eran duplicados incorrectos.
        // olt_onus_dupes_backup sirve de respaldo para restauración manual si fuera necesario.
    }
};
