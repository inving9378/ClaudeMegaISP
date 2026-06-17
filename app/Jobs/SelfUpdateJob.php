<?php

namespace App\Jobs;

use App\Models\DeploymentLog;
use App\Services\Deploy\DeploymentLock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Job de auto-actualización para instancias consumidoras.
 * Ejecuta remote:deploy (pipeline endurecido) con el tag obtenido de GitHub.
 */
class SelfUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 1;

    public function __construct(public DeploymentLog $log)
    {
    }

    public function handle(): void
    {
        if (!DeploymentLock::acquire($this->log->id)) {
            $this->log->update([
                'status'        => 'failed',
                'error_message' => 'Ya hay un deploy en curso (ID: ' . DeploymentLock::currentId() . '). Espera a que termine.',
                'finished_at'   => now(),
            ]);
            return;
        }

        try {
            $p = $this->log->payload ?? [];
            Artisan::call('remote:deploy', [
                'logId'          => $this->log->id,
                '--app-version'  => $p['version'] ?? '',
                '--title'        => $p['title'] ?? '',
                '--summary'      => $p['summary'] ?? '',
                '--release-date' => $p['release_date'] ?? now()->toDateString(),
            ]);
            Log::channel('single')->info(Artisan::output());
        } finally {
            DeploymentLock::release();
        }
    }

    public function failed(\Throwable $e): void
    {
        DeploymentLock::release();
        $this->log->update([
            'status'        => 'failed',
            'error_message' => $e->getMessage(),
            'finished_at'   => now(),
        ]);
    }
}
