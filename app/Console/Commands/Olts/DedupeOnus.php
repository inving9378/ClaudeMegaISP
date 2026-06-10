<?php

namespace App\Console\Commands\Olts;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DedupeOnus extends Command
{
    protected $signature = 'olts:dedupe-onus
                            {--execute : Ejecutar la limpieza. Sin esta opción corre en dry-run.}';

    protected $description = 'Deduplica olt_onus por unique_external_id, conservando la fila ganadora por criterio de calidad. Usa --execute para aplicar.';

    /**
     * Ejecuta o simula la deduplicación.
     * Devuelve el número de filas retiradas (o que se retiraría en dry-run).
     */
    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $mode    = $execute ? 'EJECUTANDO' : 'DRY-RUN';

        $this->line("═══ olts:dedupe-onus [{$mode}] ═══");

        $groups = $this->findDuplicateGroups();

        if (empty($groups)) {
            $this->info('Sin duplicados por unique_external_id. Nada que hacer.');
            return 0;
        }

        $this->line(sprintf('%d grupo(s) con duplicados encontrado(s).', count($groups)));

        $toDelete = [];

        foreach ($groups as $extId => $rows) {
            $winner = $this->pickWinner($rows);
            $losers = array_filter($rows, fn($r) => $r->id !== $winner->id);

            $this->line(sprintf(
                "\n  unique_external_id: %s  (%d filas)",
                $extId,
                count($rows)
            ));
            $this->line(sprintf(
                '    CONSERVAR → id=%d  olt_id=%d  status=%s  client_id=%s  last_synced_at=%s',
                $winner->id,
                $winner->olt_id,
                $winner->status ?? 'null',
                $winner->client_id ?? 'null',
                $winner->last_synced_at ?? 'null'
            ));

            foreach ($losers as $loser) {
                $this->line(sprintf(
                    '    RETIRAR   → id=%d  olt_id=%d  status=%s  client_id=%s  last_synced_at=%s',
                    $loser->id,
                    $loser->olt_id,
                    $loser->status ?? 'null',
                    $loser->client_id ?? 'null',
                    $loser->last_synced_at ?? 'null'
                ));
                $toDelete[] = $loser->id;
            }
        }

        $this->line(sprintf("\nTotal a retirar: %d fila(s).", count($toDelete)));

        if (! $execute) {
            $this->warn('Dry-run completado. Usa --execute para aplicar.');
            return 0;
        }

        // ── Ejecutar ────────────────────────────────────────────────────────
        $this->ensureBackupTable();

        DB::transaction(function () use ($toDelete) {
            // Copiar a respaldo antes de borrar
            DB::statement("
                INSERT INTO olt_onus_dupes_backup
                SELECT *, NOW() AS backed_up_at
                FROM olt_onus
                WHERE id IN (" . implode(',', $toDelete) . ")
            ");

            $deleted = DB::table('olt_onus')
                ->whereIn('id', $toDelete)
                ->delete();

            Log::info(sprintf('[GR-3c dedupe] %d fila(s) retiradas: ids [%s]', $deleted, implode(',', $toDelete)));
        });

        $this->info(sprintf('%d fila(s) retiradas y respaldadas en olt_onus_dupes_backup.', count($toDelete)));

        return 0;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Retorna grupos (keyed by unique_external_id) con 2+ filas.
     *
     * @return array<string, object[]>
     */
    public function findDuplicateGroups(): array
    {
        // IDs de unique_external_id que aparecen más de una vez
        $extIds = DB::table('olt_onus')
            ->select('unique_external_id')
            ->whereNotNull('unique_external_id')
            ->where('unique_external_id', '!=', '')
            ->groupBy('unique_external_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('unique_external_id')
            ->toArray();

        if (empty($extIds)) {
            return [];
        }

        $rows = DB::table('olt_onus')
            ->whereIn('unique_external_id', $extIds)
            ->orderBy('unique_external_id')
            ->orderByRaw("CASE WHEN status = 'Online' THEN 0 ELSE 1 END")
            ->orderByRaw('client_id IS NULL ASC')
            ->orderByDesc('last_synced_at')
            ->orderByDesc('id')
            ->get()
            ->toArray();

        $groups = [];
        foreach ($rows as $row) {
            $groups[$row->unique_external_id][] = $row;
        }

        return $groups;
    }

    /**
     * Elige el ganador de un grupo de filas con igual unique_external_id.
     *
     * Criterio (en orden):
     *   1. status = 'Online' > cualquier otro
     *   2. client_id NOT NULL > NULL
     *   3. mayor last_synced_at
     *   4. mayor id (más reciente)
     */
    public function pickWinner(array $rows): object
    {
        usort($rows, function ($a, $b) {
            // 1. Online primero
            $aOnline = ($a->status === 'Online') ? 0 : 1;
            $bOnline = ($b->status === 'Online') ? 0 : 1;
            if ($aOnline !== $bOnline) return $aOnline <=> $bOnline;

            // 2. Con cliente primero
            $aClient = is_null($a->client_id) ? 1 : 0;
            $bClient = is_null($b->client_id) ? 1 : 0;
            if ($aClient !== $bClient) return $aClient <=> $bClient;

            // 3. Más reciente last_synced_at
            if ($a->last_synced_at !== $b->last_synced_at) {
                return ($b->last_synced_at ?? '') <=> ($a->last_synced_at ?? '');
            }

            // 4. Mayor id
            return $b->id <=> $a->id;
        });

        return $rows[0];
    }

    /**
     * Crea la tabla de respaldo si no existe (esquema idéntico a olt_onus + columna backed_up_at).
     * Public para que la migración pueda invocarlo sin reflexión.
     */
    public function ensureBackupTable(): void
    {
        if (Schema::hasTable('olt_onus_dupes_backup')) {
            return;
        }

        DB::statement("
            CREATE TABLE olt_onus_dupes_backup LIKE olt_onus
        ");

        // Quitar la clave primaria y UNIQUE para que acepte duplicados
        DB::statement("ALTER TABLE olt_onus_dupes_backup MODIFY id BIGINT UNSIGNED NOT NULL");
        DB::statement("ALTER TABLE olt_onus_dupes_backup DROP PRIMARY KEY");
        DB::statement("ALTER TABLE olt_onus_dupes_backup ADD COLUMN backed_up_at DATETIME NULL");

        // Quitar el UNIQUE de unique_external_id si lo tiene (puede no existir aún)
        try {
            DB::statement("ALTER TABLE olt_onus_dupes_backup DROP INDEX olt_onus_unique_external_id_unique");
        } catch (\Throwable) {
            // Índice no existía, ignorar
        }

        // Quitar FK de service_id si la heredó
        try {
            DB::statement("ALTER TABLE olt_onus_dupes_backup DROP FOREIGN KEY olt_onus_dupes_backup_service_id_foreign");
        } catch (\Throwable) {
            // FK no existía, ignorar
        }
    }
}
