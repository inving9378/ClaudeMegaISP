<?php

namespace App\Modules\Addons\Planes\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Contratable\ClientContratableSubscription;
use App\Models\Contratable\ContratableService;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Modules\Addons\MegaFamilia\Models\ParentalDevice;
use App\Services\Contratable\ContratableSubscriptionService;
use App\Services\Contratable\ContratableTrialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pestaña "Servicios contratados" de la ficha del cliente.
 *
 * Lista los servicios contratables del catálogo (activos) y, para ESTE cliente,
 * su estado/conteo/paquete/precio + badge de prueba. Activar/Suspender/Reactivar
 * vía ContratableSubscriptionService (activated_by='admin'). NO factura (motor F2,
 * gateado). Aislado por client_id (el id viene en la URL; los conteos y la
 * suscripción scopean por ese cliente).
 */
class ClientContratableController extends Controller
{
    public function __construct(
        private ContratableSubscriptionService $subs,
        private ContratableTrialService $trial,
    ) {
    }

    /** JSON: catálogo activo + estado por cliente. */
    public function data(int $clientId): JsonResponse
    {
        $services = ContratableService::where('activo', true)
            ->with(['packages' => fn ($q) => $q->orderBy('rango_min')])
            ->orderBy('nombre')
            ->get();

        $subsByService = ClientContratableSubscription::where('client_id', $clientId)
            ->get()
            ->keyBy('contratable_service_id');

        $rows = $services->map(function (ContratableService $s) use ($clientId, $subsByService) {
            $conteo  = $this->conteo($s->metrica, $clientId);
            $package = $s->packageForQuantity($conteo);
            $sub     = $subsByService->get($s->id);

            $estado = $sub ? $sub->status : 'inactivo'; // active | suspended | inactivo
            $enPrueba = false;
            if ($sub && $sub->status === 'active') {
                // El trial necesita la relación de servicio cargada.
                $sub->setRelation('service', $s);
                $enPrueba = $this->trial->isInTrial($sub);
            }

            return [
                'service_id'   => $s->id,
                'module_key'   => $s->module_key,
                'nombre'       => $s->nombre,
                'metrica'      => $s->metrica,
                'estado'       => $estado,
                'conteo'       => $conteo,
                'paquete'      => $package ? $package->nombre : null,
                'precio'       => $package ? (float) $package->precio : null,
                'en_prueba'    => $enPrueba,
                'meses_prueba' => (int) $s->meses_prueba,
                'aplica_iva'   => (bool) $s->aplica_iva,
            ];
        });

        return response()->json(['client_id' => $clientId, 'rows' => $rows]);
    }

    public function activate(int $clientId, Request $request): JsonResponse
    {
        $serviceId = (int) $request->input('service_id');
        $this->subs->activate($clientId, $serviceId, 'admin');

        return response()->json(['success' => true]);
    }

    public function suspend(int $clientId, Request $request): JsonResponse
    {
        $serviceId = (int) $request->input('service_id');
        $this->subs->suspend($clientId, $serviceId);

        return response()->json(['success' => true]);
    }

    public function reactivate(int $clientId, Request $request): JsonResponse
    {
        $serviceId = (int) $request->input('service_id');
        $this->subs->reactivate($clientId, $serviceId);

        return response()->json(['success' => true]);
    }

    /** Conteo de unidades del cliente por métrica (mismo scope de aislamiento #1). */
    private function conteo(string $metrica, int $clientId): int
    {
        return match ($metrica) {
            ContratableService::METRICA_VEHICULOS    => FleetVehicle::forClient($clientId)->count(),
            ContratableService::METRICA_DISPOSITIVOS => ParentalDevice::forClient($clientId)->count(),
            default                                  => 0,
        };
    }
}
