<?php

namespace App\Console\Commands\Active;

use App\Jobs\CreateClientWithServiceJob;
use App\Models\ClientBundleService;
use App\Models\ClientCustomService;
use App\Models\ClientInternetService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Item roadmap #676 (sub-item de #86, fase "observabilidad"): localiza servicios con
 * mikrotik_sync_status=failed en las 3 tablas de servicio y re-despacha
 * CreateClientWithServiceJob, que ya trae reintentos nativos de Laravel con backoff
 * exponencial ($tries/backoff() — ver ese job). Este comando es el "reintento externo":
 * cuando las 5 tentativas internas de un dispatch se agotan (router caído varios
 * minutos), la fila queda marcada failed y este comando la vuelve a intentar en la
 * siguiente corrida programada (Kernel.php, cada 30 min — ventana mayor a los ~18 min
 * que tardan los 5 tries internos, para no duplicar dispatches en vuelo).
 *
 * Solo 'failed', NO 'pending': tratar pending requiere que Irving elija entre las
 * opciones A/B/C del brief de #86 (aún sin decidir) — fuera de alcance de este sub-item.
 */
class MikrotikReintentarSyncCommand extends Command
{
    protected $signature = 'mikrotik:reintentar-sync';
    protected $description = 'Reintenta la sincronización con Mikrotik de servicios marcados mikrotik_sync_status=failed';

    private const MODEL_CLASSES = [
        ClientInternetService::class,
        ClientCustomService::class,
        ClientBundleService::class,
    ];

    public function handle(): int
    {
        if (!config('mikrotik.retry.enabled', true)) {
            $this->info('mikrotik:reintentar-sync deshabilitado por config (mikrotik.retry.enabled=false).');
            return self::SUCCESS;
        }

        $batchLimit = (int) config('mikrotik.retry.batch_limit', 50);
        $circuitBreaker = (int) config('mikrotik.retry.circuit_breaker_threshold', 100);

        $totalFailed = 0;
        foreach (self::MODEL_CLASSES as $modelClass) {
            $totalFailed += $modelClass::where('mikrotik_sync_status', 'failed')->count();
        }

        if ($totalFailed === 0) {
            $this->info('Sin servicios failed pendientes de reintento.');
            return self::SUCCESS;
        }

        if ($totalFailed > $circuitBreaker) {
            $message = "mikrotik:reintentar-sync: {$totalFailed} servicios failed superan el circuit breaker "
                . "({$circuitBreaker}) — probable caída masiva de red, se omite esta corrida para no bombardear routers caídos.";
            $this->warn($message);
            Log::channel('single')->warning($message);
            return self::SUCCESS;
        }

        $dispatched = 0;
        $remaining = $batchLimit;
        foreach (self::MODEL_CLASSES as $modelClass) {
            if ($remaining <= 0) {
                break;
            }
            $services = $modelClass::where('mikrotik_sync_status', 'failed')
                ->orderBy('mikrotik_synced_at')
                ->limit($remaining)
                ->get();

            foreach ($services as $service) {
                CreateClientWithServiceJob::dispatch($service, 'App\Models\Internet');
                $dispatched++;
                $remaining--;
            }
        }

        $this->info("mikrotik:reintentar-sync: {$dispatched} servicio(s) re-despachado(s) de {$totalFailed} en failed.");
        Log::channel('single')->info("mikrotik:reintentar-sync: {$dispatched} servicio(s) re-despachado(s) de {$totalFailed} en failed.");

        return self::SUCCESS;
    }
}
