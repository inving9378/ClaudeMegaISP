<?php

namespace App\Console\Commands\Active;

use App\Services\BackupDb\MysqldumpEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class BackupDB extends Command
{
    protected $signature = 'backup_db:process';
    protected $description = 'Backup diaria de la base de datos vía mysqldump nativo + gzip';

    private const BACKUP_DIR   = '/var/backups/mysql';
    private const RETENTION_DAYS = 14;
    private const TIMEOUT        = 3600;

    public function handle(): int
    {
        $log = Log::channel('backup');

        $gzFile = self::BACKUP_DIR . '/megaisp-' . now()->format('YmdHi') . '.sql.gz';

        $log->info("Iniciando backup → {$gzFile}");
        $this->info("[backup_db] Iniciando → {$gzFile}");

        // Motor compartido: mysqldump binario por socket (--host=localhost),
        // credenciales por config() y contraseña vía MYSQL_PWD. Canaliza a gzip.
        try {
            (new MysqldumpEngine())->dumpToGzipFile($gzFile);
        } catch (\Throwable $e) {
            $log->error($e->getMessage());
            $this->error("[backup_db] FALLO mysqldump: " . $e->getMessage());
            return self::FAILURE;
        }

        // Verificar que el archivo exista y no esté vacío
        if (! file_exists($gzFile) || filesize($gzFile) === 0) {
            $log->error("Archivo inexistente o vacío: {$gzFile}");
            $this->error('[backup_db] FALLO: archivo vacío o inexistente.');
            return self::FAILURE;
        }

        // Verificar integridad del gzip
        $verify = new Process(['gzip', '-t', $gzFile]);
        $verify->run();
        if (! $verify->isSuccessful()) {
            $log->error("gzip -t falló (archivo corrupto): {$gzFile}");
            $this->error('[backup_db] FALLO: archivo .gz corrupto. Eliminando.');
            @unlink($gzFile);
            return self::FAILURE;
        }

        $sizeMb = round(filesize($gzFile) / 1048576, 2);
        $log->info("Backup OK — {$sizeMb} MB → {$gzFile}");
        $this->info("[backup_db] OK — {$sizeMb} MB → " . basename($gzFile));

        $this->applyRetention($log);

        activity()->log('Salva de BD');
        return self::SUCCESS;
    }

    private function applyRetention(object $log): void
    {
        $cutoff  = now()->subDays(self::RETENTION_DAYS)->timestamp;
        $deleted = 0;

        foreach (glob(self::BACKUP_DIR . '/megaisp-*.sql.gz') as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
                $deleted++;
                $log->info("Retención: eliminado " . basename($file));
            }
        }

        if ($deleted > 0) {
            $this->line("[backup_db] Retención: {$deleted} archivo(s) eliminado(s) (>" . self::RETENTION_DAYS . " días).");
        }
    }
}
