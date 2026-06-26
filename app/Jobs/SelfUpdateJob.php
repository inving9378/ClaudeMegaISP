<?php

namespace App\Jobs;

use App\Models\DeploymentLog;
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

    // El deploy completo (backup + git + composer + npm + migrate) puede tardar varios
    // minutos; 600s se quedaba corto al sumar la compilación. El lock TTL va alineado.
    public int $timeout = 2700;
    public int $tries   = 1;

    public function __construct(public DeploymentLog $log)
    {
    }

    public function handle(): void
    {
        // El lock lo gestiona el propio comando remote:deploy (mismo lock para el camino
        // de cola y el de nohup en modo sync), así que aquí solo lo invocamos.
        $p = $this->log->payload ?? [];
        Artisan::call('remote:deploy', [
            'logId'          => $this->log->id,
            '--app-version'  => $p['version'] ?? '',
            '--title'        => $p['title'] ?? '',
            '--summary'      => $p['summary'] ?? '',
            '--release-date' => $p['release_date'] ?? now()->toDateString(),
        ]);
        Log::channel('single')->info(Artisan::output());
    }

    public function failed(\Throwable $e): void
    {
        $this->log->update([
            'status'        => 'failed',
            'error_message' => $e->getMessage(),
            'finished_at'   => now(),
        ]);
    }
}
