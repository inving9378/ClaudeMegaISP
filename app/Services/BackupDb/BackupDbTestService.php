<?php

namespace App\Services\BackupDb;

use Ifsnop\Mysqldump as IMysqldump;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use ZipArchive;

class BackupDbTestService
{
    public function backup(string $version): bool
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $db = env('DB_DATABASE', 'forge');
        $user = env('DB_USERNAME', 'forge');
        $pass = env('DB_PASSWORD', '');
        $host = env('DB_HOST', '127.0.0.1');
        $port = env('DB_PORT', 3306);
        $basePath = storage_path("backup_test/{$version}");
        if (!File::exists($basePath)) {
            File::makeDirectory($basePath, 0755, true);
        }
        $sqlFile = "{$basePath}/{$version}.sql";
        $zipFile = "{$basePath}/{$version}.zip";

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
            $dumpMain = new IMysqldump\Mysqldump(
                $dsn,
                $user,
                $pass,
                [
                    'add-drop-table' => true,
                    'single-transaction' => true,
                    'lock-tables' => false,
                    'add-locks' => false,
                    'extended-insert' => true,
                    'skip-triggers' => false,
                    'no-data' => false,
                    'skip-comments' => false,
                    'complete-insert' => false,
                    'exclude-tables' => ['activity_log'],
                ]
            );
            $dumpMain->start($sqlFile);

            $tempStructure = "{$basePath}/activity_log_structure.sql";
            $dumpActivity = new IMysqldump\Mysqldump(
                $dsn,
                $user,
                $pass,
                [
                    'include-tables' => ['activity_log'],
                    'no-data' => true,
                    'add-drop-table' => true,
                    'single-transaction' => true,
                    'lock-tables' => false,
                ]
            );
            $dumpActivity->start($tempStructure);
            file_put_contents($sqlFile, PHP_EOL . file_get_contents($tempStructure), FILE_APPEND);
            unlink($tempStructure);
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
