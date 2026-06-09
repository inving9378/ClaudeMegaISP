<?php

namespace App\Jobs;

use App\Models\DeploymentLog;
use App\Services\Deploy\DeploymentLock;
use App\Services\Deploy\DeploymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeployJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 1;

    public function __construct(public DeploymentLog $log) {}

    public function handle(DeploymentService $service): void
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
            $service->run($this->log);
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
