<?php

namespace App\Console\Commands\Active;

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

        // Credenciales desde config(), nunca env() directamente
        $port = config('database.connections.mysql.port', '3306');
        $db   = config('database.connections.mysql.database');
        $user = config('database.connections.mysql.username');
        $pass = config('database.connections.mysql.password', '');

        // --host=localhost → mysqldump elige socket Unix (no TCP)
        $cmd = sprintf(
            'mysqldump --host=localhost --port=%s --user=%s %s | gzip > %s',
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg($db),
            escapeshellarg($gzFile)
        );

        $log->info("Iniciando backup → {$gzFile}");
        $this->info("[backup_db] Iniciando → {$gzFile}");

        $process = Process::fromShellCommandline($cmd);
        // MYSQL_PWD evita que la contraseña quede expuesta en ps/history
        $process->setEnv(['MYSQL_PWD' => $pass]);
        $process->setTimeout(self::TIMEOUT);
        $process->run();

        if (! $process->isSuccessful()) {
            $err = trim($process->getErrorOutput());
            $log->error("mysqldump falló (exit {$process->getExitCode()}): {$err}");
            $this->error("[backup_db] FALLO mysqldump: {$err}");
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
