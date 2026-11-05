<?php

namespace App\Observers;

use App\Services\Metricas\AsegurarMonitoreoDedicados;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Item #988: al cambiar lo que decide el monitoreo de un dedicado (alta o edición del servicio, estado
 * del cliente, tipo de cliente) lo deja al día. Un fallo aquí nunca debe impedir guardar el cliente
 * o el servicio: lo repara el colector en su siguiente corrida.
 */
class MonitoreoDedicadoObserver
{
    public bool $afterCommit = true;

    private const CAMPOS = ['estado', 'tipo_cliente', 'router_id', 'ipv4', 'ipv4_pool', 'deleted_at'];

    public function saved(Model $modelo): void
    {
        if ($modelo->wasRecentlyCreated || $modelo->wasChanged(self::CAMPOS)) {
            $this->asegurar($modelo);
        }
    }

    public function deleted(Model $modelo): void
    {
        $this->asegurar($modelo);
    }

    public function restored(Model $modelo): void
    {
        $this->asegurar($modelo);
    }

    private function asegurar(Model $modelo): void
    {
        $clienteId = (int) ($modelo->client_id ?? $modelo->getKey());
        try {
            app(AsegurarMonitoreoDedicados::class)->cliente($clienteId);
        } catch (Throwable $e) {
            Log::warning("metricas: no se pudo actualizar el monitoreo del cliente {$clienteId}: " . $e->getMessage());
        }
    }
}
