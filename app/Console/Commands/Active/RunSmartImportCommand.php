<?php

namespace App\Console\Commands\Active;

use App\Modules\Addons\SmartImportExport\Jobs\SmartImportJob;
use App\Modules\Addons\SmartImportExport\Models\ImportExportLog;
use App\Modules\Addons\SmartImportExport\Services\SmartImportService;
use Illuminate\Console\Command;
use Throwable;

class RunSmartImportCommand extends Command
{
    protected $signature = 'smart-import:run {jobId : UUID del import a ejecutar}';

    protected $description = 'Ejecuta un Smart Import en un proceso CLI one-shot, sin depender de queue:work';

    public function handle(): int
    {
        $jobId = (string) $this->argument('jobId');
        $context = SmartImportJob::getExecutionContext($jobId);

        if (!$context) {
            $this->markMissingContext($jobId);
            $this->error("No existe contexto de ejecución para el import {$jobId}.");
            return self::FAILURE;
        }

        $token = (string) ($context['token'] ?? '');
        $options = is_array($context['options'] ?? null) ? $context['options'] : [];
        $userId = isset($context['user_id']) ? (int) $context['user_id'] : null;
        $logId = isset($context['log_id']) ? (int) $context['log_id'] : null;

        if ($token === '') {
            $this->markMissingContext($jobId, $logId, 'No existe token de análisis para la importación.');
            $this->error("El contexto de {$jobId} no trae token de análisis.");
            return self::FAILURE;
        }

        $job = new SmartImportJob($jobId, $token, $options, $userId, $logId);

        try {
            $job->handle(app(SmartImportService::class));
            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $job->failed($e);
            $this->error($e->getMessage());
            return self::FAILURE;
        } finally {
            SmartImportJob::forgetExecutionContext($jobId);
        }
    }

    private function markMissingContext(string $jobId, ?int $logId = null, string $message = 'No existe contexto de ejecución para la importación.'): void
    {
        SmartImportJob::setStatus($jobId, [
            'state'    => 'failed',
            'progress' => 0,
            'log'      => ['Importación abortada: ' . $message],
            'error'    => $message,
        ], $logId);

        $log = $logId
            ? ImportExportLog::find($logId)
            : ImportExportLog::findByJobId($jobId);

        if ($log) {
            $log->markFailed($message);
        }
    }
}
