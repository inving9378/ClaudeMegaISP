<?php

namespace App\Console\Commands\Active;

use App\Modules\Addons\SmartImportExport\Jobs\SmartImportJob;
use Illuminate\Console\Command;

/**
 * Ejecuta un SmartImportJob de forma síncrona desde CLI.
 * Es invocado por ImportExportController::dispatchJobInBackground() mediante
 * proc_open / popen, lo que permite que el request HTTP devuelva el job_id
 * inmediatamente y el frontend vea el progreso en tiempo real via polling.
 *
 * El argumento es la ruta a un archivo JSON temporal con los parámetros del
 * job. Usar archivo en vez de argumento inline evita problemas de quoting y
 * límites de longitud de línea en Windows.
 */
class SmartImportRunJobCommand extends Command
{
    protected $signature = 'smart-import:run-job {payload_file : Ruta al archivo JSON con los parámetros del job}';

    protected $description = 'Ejecuta un SmartImportJob en background (invocado por el controlador)';

    public function handle(): int
    {
        $file = $this->argument('payload_file');

        if (!file_exists($file)) {
            $this->error("Archivo de payload no encontrado: {$file}");
            return self::FAILURE;
        }

        $params = json_decode(file_get_contents($file), true);

        // Limpiar el archivo de payload tan pronto como se lea
        @unlink($file);

        if (!$params || empty($params['job_id']) || empty($params['token'])) {
            $this->error('Payload inválido o incompleto.');
            return self::FAILURE;
        }

        SmartImportJob::dispatchSync(
            $params['job_id'],
            $params['token'],
            $params['options'] ?? [],
            $params['user_id'] ?? null,
            $params['log_id'] ?? null,
            $params['truncate_before'] ?? false,
        );

        return self::SUCCESS;
    }
}
