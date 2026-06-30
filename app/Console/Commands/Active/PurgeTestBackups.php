<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Retención de los respaldos de release que crea el pipeline de deploy
 * (BackupDbTestService → storage/backup_test/{version}/{version}.zip, ~130 MB c/u).
 *
 * Sin esto los zips se acumulan sin límite (#146). Política: keep-last-N por
 * mtime — conserva las N versiones más recientes y borra el resto. Es count-based
 * (no días) porque la cadencia de release es irregular (varias versiones el mismo
 * día), análogo en espíritu a BackupDB::applyRetention pero por conteo.
 *
 * SEGURO POR DEFECTO: sin --force solo simula (dry-run). El borrado real exige
 * --force explícito. Salvaguardas: solo toca {V}/{V}.zip con match EXACTO
 * dir==zip; cualquier dir sin su {version}.zip se OMITE; nunca wildcard amplio;
 * nunca sale de storage/backup_test/; rmdir del dir padre solo si quedó vacío.
 *
 * EJECUCIÓN REAL: en PROD (.198), donde estos zips se acumulan de verdad:
 *   php artisan backups:purge-test --force            (keep=7 por defecto)
 *   php artisan backups:purge-test --force --keep=10  (otra ventana)
 *   php artisan backups:purge-test --dry-run          (solo listar)
 */
class PurgeTestBackups extends Command
{
    protected $signature = 'backups:purge-test
                            {--keep=7 : Cuántas versiones más recientes conservar}
                            {--dry-run : Solo listar lo que se borraría, sin tocar nada}
                            {--force : Ejecutar el borrado real (sin esto, solo simula)}';

    protected $description = 'Retención de respaldos de release (storage/backup_test): conserva las N versiones más recientes y borra el resto';

    public function handle(): int
    {
        $log     = Log::channel('backup');
        $baseDir = storage_path('backup_test');
        $keep    = max(0, (int) $this->option('keep'));
        // Borrado real solo con --force; en cualquier otro caso es dry-run.
        $apply   = (bool) $this->option('force') && ! $this->option('dry-run');

        if (! is_dir($baseDir)) {
            $this->info("[purge-test] No existe {$baseDir} — nada que hacer.");
            return self::SUCCESS;
        }

        // Recolectar SOLO los respaldos válidos: {version}/{version}.zip con
        // match EXACTO (nombre de dir == nombre del zip). Todo lo demás se ignora.
        $backups = [];
        foreach (glob($baseDir . '/*', GLOB_ONLYDIR) as $dir) {
            $version = basename($dir);
            $zip     = $dir . '/' . $version . '.zip';
            if (is_file($zip)) {
                $backups[] = ['version' => $version, 'dir' => $dir, 'zip' => $zip, 'mtime' => filemtime($zip), 'size' => filesize($zip)];
            }
        }

        if (empty($backups)) {
            $this->info('[purge-test] No se encontraron respaldos de release válidos ({version}/{version}.zip).');
            return self::SUCCESS;
        }

        // Más nuevo primero
        usort($backups, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);

        $conserva = array_slice($backups, 0, $keep);
        $borrar   = array_slice($backups, $keep);

        $modo = $apply ? 'BORRADO REAL (--force)' : 'DRY-RUN (simulación)';
        $this->line("[purge-test] {$modo} — política keep-last-{$keep} · " . count($backups) . " respaldos válidos");

        $this->line("\nCONSERVA (" . count($conserva) . '):');
        foreach ($conserva as $b) {
            $this->line(sprintf('  ✓ %-22s %5d MB  %s', $b['version'], (int) ($b['size'] / 1048576), date('Y-m-d H:i', $b['mtime'])));
        }

        if (empty($borrar)) {
            $this->info("\n[purge-test] Nada que borrar: hay " . count($backups) . " respaldos ≤ keep={$keep}.");
            return self::SUCCESS;
        }

        $freed = 0;
        $count = 0;
        $this->line("\n" . ($apply ? 'BORRA' : 'BORRARÍA') . ' (' . count($borrar) . '):');
        foreach ($borrar as $b) {
            $freed += $b['size'];
            $line = sprintf('  ✗ %-22s %5d MB  %s', $b['version'], (int) ($b['size'] / 1048576), date('Y-m-d H:i', $b['mtime']));
            $this->line($line);

            if ($apply) {
                if (@unlink($b['zip'])) {
                    $count++;
                    $log->info("[purge-test] eliminado " . basename($b['zip']) . " ({$b['version']})");
                    // rmdir del dir padre SOLO si quedó vacío (no borra dirs con contenido)
                    if (count(scandir($b['dir'])) === 2) { // solo "." y ".."
                        @rmdir($b['dir']);
                    }
                } else {
                    $log->error("[purge-test] NO se pudo eliminar " . $b['zip']);
                    $this->error("    ! no se pudo eliminar {$b['zip']}");
                }
            }
        }

        $freedMb = (int) round($freed / 1048576);
        if ($apply) {
            $log->info("[purge-test] {$count} respaldo(s) eliminado(s), {$freedMb} MB liberados (keep={$keep}).");
            $this->info("\n[purge-test] OK — {$count} respaldo(s) eliminado(s), {$freedMb} MB liberados.");
        } else {
            $this->warn("\n[purge-test] DRY-RUN — borraría " . count($borrar) . " respaldo(s), liberaría {$freedMb} MB.");
            $this->line('[purge-test] Para ejecutar el borrado real: php artisan backups:purge-test --force --keep=' . $keep);
        }

        return self::SUCCESS;
    }
}
