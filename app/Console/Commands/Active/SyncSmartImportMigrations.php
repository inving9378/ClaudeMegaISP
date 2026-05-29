<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncSmartImportMigrations extends Command
{
    protected $signature = 'smart-import:sync-migrations
        {--dry-run : Solo mostrar qué migraciones se insertarían sin ejecutar}
        {--batch= : Usar batch específico en vez de auto-incrementar}';

    protected $description = 'Sincroniza la tabla migrations con los archivos en database/migrations/ tras un SmartImport';

    public function handle(): int
    {
        $dirs = array_merge(
            [database_path('migrations')],
            glob(app_path('Modules/*/*/migrations'), GLOB_ONLYDIR),
        );

        $files = [];
        foreach ($dirs as $dir) {
            foreach (glob($dir . '/*.php') as $f) {
                $files[] = $f;
            }
        }
        $files = array_unique($files);
        sort($files);

        $disk = [];
        foreach ($files as $path) {
            $name = str_replace('.php', '', basename($path));
            $disk[$name] = true;
        }

        $existingRaw = DB::table('migrations')->pluck('migration')->toArray();

        // Agrupar por nombre normalizado (sin .php)
        $existingByNorm = [];
        foreach ($existingRaw as $name) {
            $norm = str_replace('.php', '', $name);
            $existingByNorm[$norm][] = $name;
        }

        // Separar: entradas que ya están limpias vs las que solo existen con .php
        $toClean = [];
        $pending = [];
        foreach ($disk as $name => $_) {
            $entries = $existingByNorm[$name] ?? [];
            $hasClean = false;
            foreach ($entries as $entry) {
                if (str_ends_with($entry, '.php')) {
                    $toClean[] = $entry;
                } else {
                    $hasClean = true;
                }
            }
            if (!$hasClean) {
                $pending[] = $name;
            }
        }

        $maxBatch = (int) DB::table('migrations')->max('batch');

        if (empty($pending) && empty($toClean)) {
            $this->info('No hay migraciones pendientes por sincronizar.');
            return self::SUCCESS;
        }

        $batch = (int) ($this->option('batch') ?? ($maxBatch + 1));

        $this->line(sprintf('Migraciones en disco: %d', count($disk)));
        $this->line(sprintf('Migraciones en DB:   %d', count($existingRaw)));
        $this->line(sprintf('A limpiar (.php antiguos): %d', count($toClean)));
        $this->line(sprintf('Pendientes (solo tenían .php): %d', count($pending)));
        $this->line(sprintf('Batch a usar:         %d', $batch));
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('Modo dry-run — no se ejecutarán cambios:');
            if ($toClean) {
                $this->line('Registros a limpiar:');
                foreach ($toClean as $name) {
                    $this->line("  DELETE {$name}");
                }
            }
            foreach ($pending as $name) {
                $this->line("  INSERT [{$batch}] {$name}");
            }
            return self::SUCCESS;
        }

        if ($toClean !== []) {
            DB::table('migrations')->whereIn('migration', $toClean)->delete();
            $this->info(sprintf('✓ %d registros antiguos con .php eliminados.', count($toClean)));
        }

        if ($pending !== []) {
            $inserts = [];
            foreach ($pending as $name) {
                $inserts[] = [
                    'migration' => $name,
                    'batch'     => $batch,
                ];
            }
            DB::table('migrations')->insert($inserts);
            $this->info(sprintf('✓ %d migraciones insertadas en batch %d.', count($inserts), $batch));
            Log::info('SyncSmartImportMigrations: ' . count($inserts) . ' migraciones sincronizadas en batch ' . $batch);
        }

        return self::SUCCESS;
    }
}
