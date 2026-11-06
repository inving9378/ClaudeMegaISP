<?php

namespace App\Services\Metricas;

use App\Models\Router;
use App\Services\Mikrotik\MikrotikReconnectSyncService;
use Illuminate\Support\Facades\Log;

/**
 * Seguimiento up/down de un router (items #699/#701, unificado en #1171): guarda el último estado
 * conocido y, al pasar offline→online, dispara el sync masivo de sus servicios failed.
 * Lo comparten mikrotik:sync-ping y ColectorMetricas; quién lo invoca lo decide
 * config('metricas.disponibilidad_router_en_colector').
 */
final class SeguimientoDisponibilidadRouter
{
    public static function colectorLoLleva(): bool
    {
        return (bool) config('metricas.disponibilidad_router_en_colector', false);
    }

    public static function registrar(Router $router, bool $isUp, string $origen): void
    {
        $newStatus = $isUp ? 'up' : 'down';
        $previousStatus = $router->mikrotik_last_status;

        if ($previousStatus === $newStatus) {
            return;
        }

        $changedAt = now();

        if ($previousStatus === 'down' && $newStatus === 'up') {
            $before = $router->mikrotik_status_changed_at ?: 'sin registro previo';
            Log::info(
                "{$origen}: router {$router->id} ({$router->title}) recuperó conexión (offline→online) "
                . "— antes: {$before}, después: {$changedAt->toDateTimeString()} — disparando sync masivo (item #701)."
            );
        }

        $router->update([
            'mikrotik_last_status'       => $newStatus,
            'mikrotik_status_changed_at' => $changedAt,
        ]);

        if ($previousStatus === 'down' && $newStatus === 'up') {
            MikrotikReconnectSyncService::dispatchForRouter($router);
        }
    }
}
