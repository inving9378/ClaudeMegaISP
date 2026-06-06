<?php

namespace App\Console\Commands\Active;

use Carbon\Carbon;
use Ifsnop\Mysqldump as IMysqldump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class BackupDB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup_db:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup diaria de la base de datos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        set_time_limit(0);
        ini_set('memory_limit', '8912M');
        $now = Carbon::now()->toDateString() . '-' . Carbon::now()->timestamp;
        // Usar config() en vez de env(): con la config cacheada (config:cache)
        // env() devuelve null fuera de los archivos de config y caería a los
        // defaults 'forge'/'' provocando "Access denied for user 'forge'".
        $host = config('database.connections.mysql.host', '127.0.0.1');
        $port = config('database.connections.mysql.port', '3306');
        $db = config('database.connections.mysql.database', 'forge');
        $user = config('database.connections.mysql.username', 'forge');
        $pass = config('database.connections.mysql.password', '');
        $backupPath = storage_path('backup');
        $sqlFile = $backupPath . '/dump-' . $now . '.sql';
        $zipFile = $backupPath . '/dump-' . $now . '.zip';

        try {
            // Create SQL dump
            $dump = new IMysqldump\Mysqldump('mysql:host=' . $host . ';port=' . $port . ';dbname=' . $db, $user, $pass);
            $dump->start($sqlFile);

            // Compress the SQL dump into a ZIP file
            $zip = new ZipArchive();
            if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
                $zip->addFile($sqlFile, basename($sqlFile));
                $zip->setCompressionName(basename($sqlFile), ZipArchive::CM_DEFLATE);
                $zip->close();

                // Delete the original SQL file after zipping
                unlink($sqlFile);
            } else {
                Log::error('Error al crear el archivo ZIP');
            }
        } catch (\Exception $e) {
            Log::error('mysqldump-php error: ' . $e->getMessage());
            return;
        }

        Log::info('Backup de base de datos guardado y comprimido.');

        activity()->log('Salva de BD');
    }
}
