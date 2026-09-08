<?php

namespace App\Modules\Addons\GestionRed\Repositories;

use App\Models\ClientBundleService;
use App\Models\ClientCustomService;
use App\Models\ClientInternetService;
use Illuminate\Database\Eloquent\Model;

/**
 * Item roadmap #677: lectura read-only de servicios con mikrotik_sync_status
 * pending/failed en las 3 tablas de servicio (mismo universo que
 * MikrotikReintentarSyncCommand del item #676), para el dashboard de
 * GestionRed y el botón "Reintentar sync" por fila.
 */
class MikrotikSyncRepository
{
    public const TIPOS = [
        'internet' => ClientInternetService::class,
        'custom' => ClientCustomService::class,
        'bundle' => ClientBundleService::class,
    ];

    /**
     * @param string|null $status 'pending'|'failed'|null (ambos)
     * @return array<int, array<string, mixed>>
     */
    public function pendientesOFallidos(?string $status = null): array
    {
        $statuses = $status ? [$status] : ['pending', 'failed'];
        $rows = [];

        foreach (self::TIPOS as $tipo => $modelClass) {
            // client_bundle_services no tiene columna router_id (no hay relación router()).
            $withRouter = $modelClass !== ClientBundleService::class;
            $relations = $withRouter
                ? ['client.client_main_information', 'router']
                : ['client.client_main_information'];

            $services = $modelClass::whereIn('mikrotik_sync_status', $statuses)
                ->with($relations)
                ->get();

            foreach ($services as $service) {
                $rows[] = $this->formatear($tipo, $service, $withRouter);
            }
        }

        usort($rows, fn ($a, $b) => strcmp((string) $b['fecha'], (string) $a['fecha']));

        return $rows;
    }

    public function encontrar(string $tipo, int $id): ?Model
    {
        $modelClass = self::TIPOS[$tipo] ?? null;
        if (!$modelClass) {
            return null;
        }

        return $modelClass::find($id);
    }

    private function formatear(string $tipo, Model $service, bool $withRouter): array
    {
        $clientName = $service->client?->client_main_information?->client_name_with_fathers_names;

        return [
            'tipo' => $tipo,
            'tipo_label' => $this->tipoLabel($tipo),
            'id' => $service->id,
            'client_id' => $service->client_id,
            'client_name' => $clientName ?: "Cliente #{$service->client_id}",
            'router' => $withRouter ? ($service->router->title ?? null) : null,
            'status' => $service->mikrotik_sync_status,
            'error' => $service->mikrotik_sync_error,
            'fecha' => optional($service->mikrotik_synced_at)->toDateTimeString(),
        ];
    }

    private function tipoLabel(string $tipo): string
    {
        return match ($tipo) {
            'internet' => 'Internet',
            'custom' => 'Custom',
            'bundle' => 'Bundle',
            default => ucfirst($tipo),
        };
    }
}
