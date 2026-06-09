<?php

namespace App\Console\Commands\Active;

use App\Models\DeploymentLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RunPendingDeploysCommand extends Command
{
    protected $signature = 'remote:deploy-run-pending';

    protected $description = 'Detecta DeploymentLogs en estado pending (creados por el webhook) y ejecuta el deploy real. Lo invoca el scheduler cada minuto.';

    public function handle(): int
    {
        // Solo logs recientes: evita re-ejecutar deploys viejos si el servidor estuvo caído.
        $pending = DeploymentLog::where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(20))
            ->orderBy('id')
            ->get();

        if ($pending->isEmpty()) {
            return 0;
        }

        foreach ($pending as $log) {
            // Re-chequeo atómico: marca running ANTES de correr para que un segundo
            // ciclo del scheduler no lo tome (defensa extra al withoutOverlapping).
            $claimed = DeploymentLog::where('id', $log->id)
                ->where('status', 'pending')
                ->update(['status' => 'running']);

            if (!$claimed) {
                continue; // otro proceso ya lo tomó
            }

            $p = $log->payload ?? [];
            $this->info("Ejecutando deploy pendiente — log #{$log->id} (version=" . ($p['version'] ?? '') . ")");

            Artisan::call('remote:deploy', [
                'logId'          => $log->id,
                '--version'      => $p['version'] ?? '',
                '--title'        => $p['title'] ?? '',
                '--summary'      => $p['summary'] ?? '',
                '--release-date' => $p['release_date'] ?? now()->toDateString(),
            ]);

            $this->line(Artisan::output());
        }

        return 0;
    }
}
