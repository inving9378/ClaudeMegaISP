<?php

namespace App\Services\Mikrotik;

use App\Jobs\CreateClientWithServiceJob;
use App\Models\ClientCustomService;
use App\Models\ClientInternetService;
use App\Models\Router;
use Illuminate\Support\Facades\Log;

/**
 * Item roadmap #701 (Fase 2 de #678, depende de #676 y #699 — ambos completados): al
 * detectar que un router Mikrotik pasó de offline→online (ver
 * SyncPingMonitoring::trackRouterAvailability, item #699), dispara EN LOTE el
 * re-despacho de CreateClientWithServiceJob para todos los servicios de ESE router que
 * quedaron marcados mikrotik_sync_status=failed — reusando el mismo mecanismo del
 * item #676 (MikrotikReintentarSyncCommand), NO un reintento propio.
 *
 * client_bundle_services queda fuera a propósito: no tiene columna router_id (su vínculo
 * con el router es indirecto vía sus servicios internet/custom hijos, que sí se procesan
 * aquí) y CreateClientWithServiceJob nunca se despacha con un bundle como argumento en
 * ningún punto del codebase actual.
 */
class MikrotikReconnectSyncService
{
    private const MODEL_CLASSES = [
        ClientInternetService::class,
        ClientCustomService::class,
    ];

    public static function dispatchForRouter(Router $router): int
    {
        if (!config('mikrotik.reconnect_sync.enabled', true)) {
            return 0;
        }

        $limit = (int) config('mikrotik.reconnect_sync.batch_limit_per_router', 100);
        $dispatched = 0;

        foreach (self::MODEL_CLASSES as $modelClass) {
            if ($dispatched >= $limit) {
                break;
            }

            $services = $modelClass::where('router_id', $router->id)
                ->where('mikrotik_sync_status', 'failed')
                ->orderBy('mikrotik_synced_at')
                ->limit($limit - $dispatched)
                ->get();

            foreach ($services as $service) {
                CreateClientWithServiceJob::dispatch($service, 'App\Models\Internet');
                $dispatched++;
            }
        }

        if ($dispatched > 0) {
            Log::channel('single')->info(
                "MikrotikReconnectSyncService: router {$router->id} ({$router->title}) reconectó "
                . "(offline→online) — {$dispatched} servicio(s) failed re-despachado(s) en lote (item #701)."
            );
        }

        return $dispatched;
    }
}
