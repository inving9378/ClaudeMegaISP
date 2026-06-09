<?php

namespace App\Console\Commands\Active;

use App\Models\DeploymentLog;
use App\Services\Deploy\DeploymentLock;
use App\Services\Deploy\DeploymentService;
use Illuminate\Console\Command;

class DeployReleaseCommand extends Command
{
    protected $signature = 'release:deploy {deploymentId : ID del deployment_log a ejecutar}';
    protected $description = 'Ejecuta el pipeline de release (build → commit → push → migrate)';

    public function handle(DeploymentService $service): int
    {
        $log = DeploymentLog::with('release')->findOrFail($this->argument('deploymentId'));

        if (!DeploymentLock::acquire($log->id)) {
            $this->error('Ya hay un deploy en curso (ID: ' . DeploymentLock::currentId() . ').');
            return 1;
        }

        try {
            $version = $log->release?->version ?? '';
            $title   = $log->release?->title   ?? '';
            $service->run($log, $version, $title);
            $this->info("Deploy #{$log->id} completado con status: {$log->fresh()->status}");
        } finally {
            DeploymentLock::release();
        }

        return 0;
    }
}
