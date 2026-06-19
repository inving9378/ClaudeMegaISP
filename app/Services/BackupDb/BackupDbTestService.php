<?php

namespace App\Services\BackupDb;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use ZipArchive;

class BackupDbTestService
{
    public function backup(string $version): bool
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $basePath = storage_path("backup_test/{$version}");
        if (!File::exists($basePath)) {
            File::makeDirectory($basePath, 0755, true);
        }
        $sqlFile = "{$basePath}/{$version}.sql";
        $zipFile = "{$basePath}/{$version}.zip";

        try {
            $engine = new MysqldumpEngine();

            // Volcado principal (datos + estructura) excluyendo activity_log
            $mainSql = $engine->dump([
                'flags' => [
                    '--single-transaction',
                    '--skip-lock-tables',
                    '--skip-add-locks',
                    '--add-drop-table',
                    '--default-character-set=utf8mb4',
                ],
                'ignore_tables' => ['activity_log'],
            ]);

            // Solo la estructura de activity_log (sin datos), anexada al final
            $activitySql = $engine->dump([
                'flags' => [
                    '--single-transaction',
                    '--skip-lock-tables',
                    '--add-drop-table',
                    '--no-data',
                    '--default-character-set=utf8mb4',
                ],
                'tables' => ['activity_log'],
            ]);

            file_put_contents($sqlFile, $mainSql . PHP_EOL . $activitySql);

            $zip = new ZipArchive();
            if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $zip->addFile($sqlFile, basename($sqlFile));
                $zip->setCompressionName(basename($sqlFile), ZipArchive::CM_DEFLATE);
                $zip->close();
                unlink($sqlFile);
                Log::info("✅ Backup DB creado correctamente (activity_log vacía): {$zipFile}");
            } else {
                Log::error("❌ Error al crear ZIP para versión {$version}");
                return false;
            }
        } catch (\Throwable $e) {
            Log::error("❌ Backup falló para versión {$version}: " . $e->getMessage());
            return false;
        }

        return true;
    }
}
