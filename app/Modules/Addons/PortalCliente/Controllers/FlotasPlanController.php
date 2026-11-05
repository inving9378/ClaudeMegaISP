<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Flotas\Services\FleetSubscriptionService;
use App\Modules\Addons\Flotas\Support\FleetPlans;
use App\Services\Tenant\CurrentClientResolver;
use Illuminate\Http\JsonResponse;

/**
 * Resumen de plan de Flotas del cliente, SOLO LECTURA (item #658, Portal=Panel F2b).
 * Sin acciones de plan/suscripción: el cliente solo ve su bloque, unidades, precio y fin de prueba/promoción.
 */
class FlotasPlanController extends Controller
{
    public function __construct(
        private CurrentClientResolver $resolver,
        private FleetSubscriptionService $subs,
    ) {
    }

    public function show(): JsonResponse
    {
        $clientId = $this->resolver->resolve();
        abort_unless($clientId, 403, 'Sesión sin cliente.');

        $sub = $this->subs->forClient((int) $clientId);
        abort_unless($sub && $sub->isUsable(), 403, 'Tu servicio de Flotas no está activo. Solicita su activación.');

        $plan = FleetPlans::get((string) $sub->plan);
        $units = (int) $sub->vehicles_count;

        return response()->json(['plan' => [
            'bloque'          => $plan['name'] ?? $sub->plan,
            'unidades'        => $units,
            'unidades_incluidas' => (int) $sub->included_units,
            'precio_mensual'  => FleetPlans::priceForUnits($units),
            'estado'          => $sub->status,
            'es_promo'        => (bool) $sub->is_promo,
            'fin_prueba'      => $sub->trial_ends_at?->toDateString(),
        ]]);
    }
}
