<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Flotas\Models\FleetSubscription;
use App\Modules\Addons\Flotas\Services\FleetSubscriptionService;
use App\Modules\Addons\Flotas\Support\FleetPlans;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Administración comercial de las suscripciones SaaS de Flotas.
 * Operado por Meganet (admin). Todas las acciones de cobro real son Fase 6.2.
 */
class FleetSubscriptionController extends Controller
{
    public function __construct(private FleetSubscriptionService $service)
    {
    }

    // GET /flotas/suscripciones/dashboard — lista paginada con KPIs
    public function index(Request $request): JsonResponse
    {
        $this->authorize('fleet.subscriptions.manage');

        $query = FleetSubscription::withTrashed(false)
            ->with('client:id,name')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->plan,   fn ($q, $p) => $q->where('plan', $p))
            ->latest('id');

        $subs = $query->paginate(25);

        $kpis = DB::table('fleet_subscriptions')
            ->whereNull('deleted_at')
            ->selectRaw("
                COUNT(*) AS total,
                SUM(status = 'active')  AS activas,
                SUM(status = 'trial')   AS en_trial,
                SUM(status IN ('cancelled','expired')) AS bajas,
                COALESCE(SUM(CASE WHEN status = 'active' THEN monthly_price ELSE 0 END), 0) AS mrr
            ")
            ->first();

        return response()->json(['kpis' => $kpis, 'subscriptions' => $subs, 'plans' => FleetPlans::all()]);
    }

    // GET /flotas/suscripciones/catalog
    public function catalog(): JsonResponse
    {
        $this->authorize('fleet.subscriptions.manage');

        return response()->json(['plans' => $this->service->catalog()]);
    }

    // GET /flotas/suscripciones/client/{clientId}
    public function forClient(int $clientId): JsonResponse
    {
        $this->authorize('fleet.subscriptions.manage');

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

    // POST /flotas/suscripciones/client/{clientId}/start-trial
    public function startTrial(Request $request, int $clientId): JsonResponse
    {
        $this->authorize('fleet.subscriptions.manage');

        $data = $request->validate([
            'plan' => ['required', 'string', 'in:' . implode(',', FleetPlans::keys())],
        ]);

        $sub = $this->service->startTrial($clientId, $data['plan']);

        return response()->json(['ok' => true, 'subscription' => $sub], 201);
    }

    // POST /flotas/suscripciones/client/{clientId}/change-plan
    public function changePlan(Request $request, int $clientId): JsonResponse
    {
        $this->authorize('fleet.subscriptions.manage');

        $data = $request->validate([
            'plan' => ['required', 'string', 'in:' . implode(',', FleetPlans::keys())],
        ]);

        $sub = $this->service->forClient($clientId);
        if (! $sub) {
            // Si no tiene suscripción, iniciar trial en el plan indicado
            $sub = $this->service->startTrial($clientId, $data['plan']);
            return response()->json(['ok' => true, 'subscription' => $sub], 201);
        }

        $sub = $this->service->changePlan($sub, $data['plan']);

        return response()->json(['ok' => true, 'subscription' => $sub]);
    }

    // POST /flotas/suscripciones/client/{clientId}/cancel
    public function cancel(Request $request, int $clientId): JsonResponse
    {
        $this->authorize('fleet.subscriptions.manage');

        $data = $request->validate(['reason' => 'nullable|string|max:255']);

        $sub = $this->service->forClient($clientId);
        if (! $sub) {
            return response()->json(['ok' => false, 'error' => 'Sin suscripción activa'], 404);
        }

        $sub = $this->service->cancel($sub, $data['reason'] ?? null);

        return response()->json(['ok' => true, 'subscription' => $sub]);
    }

    // confirmPayment() existe pero NO está routeado — reservado para Fase 6.2 (integración Pagos).
}
