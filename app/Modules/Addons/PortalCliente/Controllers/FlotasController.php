<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Flotas\Models\FleetGeofence;
use App\Modules\Addons\Flotas\Models\FleetMaintenance;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Modules\Addons\Flotas\Services\FleetPositionService;
use App\Modules\Addons\Flotas\Services\FleetSubscriptionService;
use App\Services\Tenant\CurrentClientResolver;
use Illuminate\Support\Facades\Auth;

/**
 * Panel "Mi Flota" en el portal de cliente (guard `cliente`). Solo lectura.
 *
 * client_id viene de CurrentClientResolver::resolve() (rama guard cliente) y TODA
 * consulta se scopea con ->forClient($clientId). Los vehículos internos de Meganet
 * (client_id NULL) jamás aparecen: forClient($clientId concreto) filtra
 * WHERE client_id = $clientId. Reusa FleetSubscriptionService (no duplica lógica).
 */
class FlotasController extends Controller
{
    public function index(CurrentClientResolver $resolver, FleetSubscriptionService $subs, FleetPositionService $positions)
    {
        $clientId = $resolver->resolve();
        $cmi      = Auth::guard('cliente')->user();

        // Fail-closed: sin client_id resuelto no se expone nada.
        $subscription = $clientId ? $subs->forClient($clientId) : null;
        $vehicles     = $clientId
            ? FleetVehicle::forClient($clientId)
                ->orderBy('plates')
                ->get(['id', 'plates', 'brand', 'model', 'year', 'vehicle_type', 'status'])
            : collect();

        // Sin flota (ni vehículos ni suscripción) → estado vacío con CTA, NO error.
        if ($vehicles->isEmpty() && ! $subscription) {
            return view('addon-portal-cliente::flotas', [
                'cmi'    => $cmi,
                'active' => false,
            ]);
        }

        $vehicleIds = $vehicles->pluck('id');

        // Mantenimientos próximos 30 días, scopeados por los vehículos del cliente.
        $upcomingMaint = $vehicleIds->isEmpty() ? 0 : FleetMaintenance::whereIn('vehicle_id', $vehicleIds)
            ->where('is_draft', false)
            ->whereNotNull('next_service_date')
            ->whereBetween('next_service_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->count();

        $standing = [
            'total'          => $vehicles->count(),
            'activos'        => $vehicles->where('status', 'active')->count(),
            'mant_proximos'  => $upcomingMaint,
            'geocercas'      => $clientId ? FleetGeofence::forClient($clientId)->where('active', true)->count() : 0,
        ];

        // Rastreo (solo lectura): última posición conocida por vehículo con GPS activo.
        // getCurrentPositions() ya scopea por forClient($clientId) — mismo candado fail-closed.
        $tracking = $positions->getCurrentPositions($clientId)->keyBy('vehicle_id');

        return view('addon-portal-cliente::flotas', [
            'cmi'          => $cmi,
            'active'       => true,
            'subscription' => $subscription,
            'vehicles'     => $vehicles,
            'standing'     => $standing,
            'tracking'     => $tracking,
        ]);
    }
}
