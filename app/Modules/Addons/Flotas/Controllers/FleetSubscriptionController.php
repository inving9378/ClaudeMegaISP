<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Flotas\Models\FleetSubscription;
use App\Modules\Addons\Flotas\Services\FleetSubscriptionService;
use App\Modules\Addons\Flotas\Support\FleetPlans;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Administración comercial de las suscripciones SaaS de Flotas.
 * Lo opera Meganet (admin) desde la ficha del cliente ISP — por eso filtra por client_id de la ruta.
 */
class FleetSubscriptionController extends Controller
{
    public function __construct(private FleetSubscriptionService $service)
    {
    }

    // GET /flotas/api/suscripciones/catalogo — planes disponibles
    public function catalog(): JsonResponse
    {
        $this->authorize('fleet.view');

        return response()->json(['plans' => $this->service->catalog()]);
    }

    // GET /flotas/api/suscripciones/cliente/{clientId} — suscripción actual del cliente
    public function forClient(int $clientId): JsonResponse
    {
        $this->authorize('fleet.view');

        $sub = $this->service->forClient($clientId);
        if ($sub) {
            $this->service->syncVehicleCount($sub);
            $sub = $sub->fresh()->load('events:id,subscription_id,event_type,metadata,occurred_at');
        }

        return response()->json([
            'subscription' => $sub,
            'plans'        => $this->service->catalog(),
            'has_access'   => $sub?->isUsable() ?? false,
        ]);
    }

    // POST /flotas/api/suscripciones/cliente/{clientId}/activar — inicia trial (o reactiva)
    public function activate(Request $request, int $clientId): JsonResponse
    {
        $this->authorize('fleet.manage');

        $data = $request->validate([
            'plan' => ['required', 'string', 'in:' . implode(',', FleetPlans::keys())],
        ]);

        $sub = $this->service->startTrial($clientId, $data['plan']);

        return response()->json(['ok' => true, 'subscription' => $sub], 201);
    }

    // POST /flotas/api/suscripciones/{id}/plan — cambia el plan
    public function changePlan(Request $request, int $id): JsonResponse
    {
        $this->authorize('fleet.manage');

        $data = $request->validate([
            'plan' => ['required', 'string', 'in:' . implode(',', FleetPlans::keys())],
        ]);

        $sub = $this->service->changePlan($this->find($id), $data['plan']);

        return response()->json(['ok' => true, 'subscription' => $sub]);
    }

    // POST /flotas/api/suscripciones/{id}/confirmar-pago — convierte trial en suscripción pagada
    public function confirmPayment(int $id): JsonResponse
    {
        $this->authorize('fleet.manage');

        $sub = $this->service->activate($this->find($id));

        return response()->json(['ok' => true, 'subscription' => $sub]);
    }

    // POST /flotas/api/suscripciones/{id}/cancelar
    public function cancel(Request $request, int $id): JsonResponse
    {
        $this->authorize('fleet.manage');

        $data = $request->validate(['reason' => 'nullable|string|max:255']);
        $sub  = $this->service->cancel($this->find($id), $data['reason'] ?? null);

        return response()->json(['ok' => true, 'subscription' => $sub]);
    }

    private function find(int $id): FleetSubscription
    {
        return FleetSubscription::findOrFail($id);
    }
}
