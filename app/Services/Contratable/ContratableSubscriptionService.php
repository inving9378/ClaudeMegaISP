<?php

namespace App\Services\Contratable;

use App\Models\Contratable\ClientContratableSubscription;
use App\Models\Contratable\ContratableService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Alta/baja de suscripciones de cliente a servicios contratables.
 *
 * Guard-agnostic y reutilizable: la vía admin (ficha) y la futura vía portal del
 * cliente llaman a los mismos métodos. NO factura (eso es el motor F2, gateado);
 * solo crea/cambia el estado de la suscripción.
 *
 * Idempotente por el UNIQUE (client_id, contratable_service_id): si ya existe, la
 * reactiva o la devuelve tal cual; nunca duplica.
 */
class ContratableSubscriptionService
{
    private const ORIGENES = ['admin', 'client'];

    /**
     * Activa (o reactiva) la suscripción de un cliente a un servicio.
     *
     * @param  string  $activatedBy  'admin' (ficha) | 'client' (portal, futuro)
     */
    public function activate(int $clientId, int $serviceId, string $activatedBy = 'admin'): ClientContratableSubscription
    {
        if (! in_array($activatedBy, self::ORIGENES, true)) {
            throw new InvalidArgumentException("Origen de activación inválido: {$activatedBy}");
        }

        $service = ContratableService::findOrFail($serviceId);

        return DB::transaction(function () use ($clientId, $service, $activatedBy) {
            $sub = ClientContratableSubscription::where('client_id', $clientId)
                ->where('contratable_service_id', $service->id)
                ->first();

            if ($sub) {
                if ($sub->status !== 'active') {
                    $sub->status = 'active';
                    $sub->suspended_at = null;
                    if (! $sub->activated_at) {
                        $sub->activated_at = now();
                    }
                    $sub->save();
                }

                return $sub;
            }

            return ClientContratableSubscription::create([
                'client_id'                => $clientId,
                'contratable_service_id'   => $service->id,
                'status'                   => 'active',
                'activated_at'             => now(),
                'activated_by'             => $activatedBy,
                'trial_invoices_remaining' => $service->meses_prueba, // informativo (la prueba real es stateless)
                'created_by'               => $activatedBy === 'admin' ? Auth::id() : null,
            ]);
        });
    }

    /** Suspende la suscripción (no borra datos). Solo admin (lo enforza el controlador). */
    public function suspend(int $clientId, int $serviceId): ?ClientContratableSubscription
    {
        $sub = $this->find($clientId, $serviceId);

        if ($sub && $sub->status !== 'suspended') {
            $sub->status = 'suspended';
            $sub->suspended_at = now();
            $sub->save();
        }

        return $sub;
    }

    /** Reactiva una suscripción suspendida (no crea nueva). */
    public function reactivate(int $clientId, int $serviceId): ?ClientContratableSubscription
    {
        $sub = $this->find($clientId, $serviceId);

        if ($sub && $sub->status !== 'active') {
            $sub->status = 'active';
            $sub->suspended_at = null;
            if (! $sub->activated_at) {
                $sub->activated_at = now();
            }
            $sub->save();
        }

        return $sub;
    }

    private function find(int $clientId, int $serviceId): ?ClientContratableSubscription
    {
        return ClientContratableSubscription::where('client_id', $clientId)
            ->where('contratable_service_id', $serviceId)
            ->first();
    }
}
