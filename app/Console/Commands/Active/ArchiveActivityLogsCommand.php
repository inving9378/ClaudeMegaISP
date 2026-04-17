<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ArchiveActivityLogsCommand extends Command
{
    protected $signature = 'activitylog:archive
                            {--days=90 : Archivar logs más antiguos que este número de días}
                            {--batch=200 : Registros por lote}
                            {--dry-run : Solo muestra cuántos registros se moverían, sin hacer cambios}';

    protected $description = 'Mueve activity_logs antiguos a la BD de archivo (meganet_logs) para reducir el tamaño del backup principal';

    public function handle(): int
    {
        $days   = (int) $this->option('days');
        $batch  = (int) $this->option('batch');
        $dryRun = $this->option('dry-run');
        $cutoff = now()->subDays($days)->toDateTimeString();

        $this->info("Fecha de corte: registros anteriores a {$cutoff} ({$days} días)");

        $total = DB::table('activity_log')
            ->where('created_at', '<', $cutoff)
            ->count();

        if ($total === 0) {
            $this->info('No hay registros para archivar.');
            return self::SUCCESS;
        }

        $this->info("Registros a archivar: {$total}");

        if ($dryRun) {
            $this->warn('[dry-run] No se realizaron cambios.');
            return self::SUCCESS;
        }

        $this->ensureArchiveTableExists();

        $moved  = 0;
        $errors = 0;
        $bar    = $this->output->createProgressBar((int) ceil($total / $batch));
        $bar->start();

        DB::table('activity_log')
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById($batch, function ($records) use (&$moved, &$errors, $bar) {
                $rows = $records->map(fn($r) => (array) $r)->toArray();
                $ids  = array_column($rows, 'id');

                try {
                    // insertOrIgnore evita duplicados si el comando se re-ejecuta
                    DB::connection('logs')->table('activity_log')->insertOrIgnore($rows);

                    // Solo borramos del principal si la inserción fue exitosa
                    DB::table('activity_log')->whereIn('id', $ids)->delete();

                    $moved += count($rows);
                } catch (\Illuminate\Database\QueryException $e) {
                    // Intentar reconectar y reintentar el lote una vez
                    try {
                        DB::reconnect('logs');
                        DB::connection('logs')->table('activity_log')->insertOrIgnore($rows);
                        DB::table('activity_log')->whereIn('id', $ids)->delete();
                        $moved += count($rows);
                    } catch (\Exception $retryEx) {
                        $errors++;
                        $this->newLine();
                        $this->error('Error en lote (id ' . min($ids) . '-' . max($ids) . '): ' . $retryEx->getMessage());
                    }
                }

                $bar->advance();

                // Pequeña pausa cada 50 lotes para no saturar MySQL
                if (($moved / count($rows)) % 50 === 0) {
                    usleep(100000); // 100ms
                }
            });

        $bar->finish();
        $this->newLine();
        $this->info("Archivados: {$moved} registros.");

        if ($errors > 0) {
            $this->warn("Lotes con error: {$errors}. Puedes re-ejecutar el comando para reintentar los pendientes.");
        }

        return self::SUCCESS;
    }

    private function ensureArchiveTableExists(): void
    {
        $dbName = config('database.connections.logs.database');

        // Intentar crear la BD — puede fallar si el usuario no tiene permisos (ej. en prod)
        // En ese caso se asume que la BD ya fue creada manualmente por el DBA
        try {
            DB::statement("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Exception $e) {
            $this->line("  BD '{$dbName}' no creada por el comando (sin permisos o ya existe) — continuando...");
        }

        // Ahora crear la tabla dentro de la BD de archivo
        DB::connection('logs')->statement("
            CREATE TABLE IF NOT EXISTS `activity_log` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `log_name` varchar(255) DEFAULT NULL,
                `description` text NOT NULL,
                `subject_type` varchar(255) DEFAULT NULL,
                `subject_id` bigint unsigned DEFAULT NULL,
                `event` varchar(255) DEFAULT NULL,
                `causer_type` varchar(255) DEFAULT NULL,
                `causer_id` bigint unsigned DEFAULT NULL,
                `properties` json DEFAULT NULL,
                `batch_uuid` char(36) DEFAULT NULL,
                `client_id` bigint unsigned DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT NULL,
                `updated_at` timestamp NULL DEFAULT NULL,
                INDEX `activity_log_log_name_index` (`log_name`),
                INDEX `subject` (`subject_type`, `subject_id`),
                INDEX `causer` (`causer_type`, `causer_id`),
                INDEX `activity_log_client_id_index` (`client_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
