<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Modules\Addons\Flotas\Services\FleetGpsDeviceService;
use App\Modules\Addons\Flotas\Services\FleetSubscriptionService;
use App\Modules\Addons\Flotas\Support\FleetVehicleRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Unidades de un cliente registradas por el personal desde la ficha del cliente.
 * Siempre con client_id EXPLÍCITO (nunca interno Meganet). El panel /flotas no cambia.
 */
class FleetClientVehicleController extends Controller
{
    public function __construct(private FleetSubscriptionService $subs, private FleetGpsDeviceService $gps)
    {
    }

    public function index(int $clientId): JsonResponse
    {
        $this->authorizeStaff();
        $this->assertClient($clientId);

        $vehicles = FleetVehicle::where('client_id', $clientId)->orderBy('brand')->orderBy('model')->get()
            ->each(fn ($v) => $v->setAttribute('gps', $this->gps->resumen($v)));

        return response()->json(['vehicles' => $vehicles]);
    }

    public function store(Request $request, int $clientId): JsonResponse
    {
        $this->authorizeStaff();
        $this->assertClient($clientId);

        $data = $request->validate(FleetVehicleRules::store());
        $data['client_id'] = $clientId;

        $antes   = FleetVehicle::where('client_id', $clientId)->count();
        $vehicle = FleetVehicle::create($data);

        return response()->json(['vehicle' => $vehicle, 'aviso_bloque' => $this->sync($clientId, $antes)], 201);
    }

    public function update(Request $request, int $clientId, int $id): JsonResponse
    {
        $this->authorizeStaff();
        $this->assertClient($clientId);

        $vehicle = FleetVehicle::where('client_id', $clientId)->findOrFail($id);
        $vehicle->update($request->validate(FleetVehicleRules::update()));

        return response()->json(['vehicle' => $vehicle->fresh()]);
    }

    public function destroy(int $clientId, int $id): JsonResponse
    {
        $this->authorizeStaff();
        $this->assertClient($clientId);

        $vehicle = FleetVehicle::where('client_id', $clientId)->findOrFail($id);
        $antes   = FleetVehicle::where('client_id', $clientId)->count();
        $vehicle->delete();

        return response()->json(['ok' => true, 'aviso_bloque' => $this->sync($clientId, $antes)]);
    }

    public function storeGps(Request $request, int $clientId, int $id): JsonResponse
    {
        $this->authorizeStaff();
        $this->assertClient($clientId);

        $vehicle = FleetVehicle::where('client_id', $clientId)->findOrFail($id);
        $r = $this->gps->vincular($vehicle, $request->all());

        return response()->json(['gps' => $this->gps->resumen($vehicle), 'listener' => $r['listener']], 201);
    }

    public function destroyGps(int $clientId, int $id): JsonResponse
    {
        $this->authorizeStaff();
        $this->assertClient($clientId);

        $this->gps->quitar(FleetVehicle::where('client_id', $clientId)->findOrFail($id));

        return response()->json(['ok' => true]);
    }

    /** Recalcula el conteo de la suscripción y devuelve el aviso si cambió de bloque. */
    private function sync(int $clientId, int $antes): ?array
    {
        $sub = $this->subs->forClient($clientId);
        if (! $sub) {
            return null;
        }
        $this->subs->syncVehicleCount($sub);
        $despues = FleetVehicle::where('client_id', $clientId)->count();

        return $this->subs->avisarCambioDeBloque($sub, $antes, $despues);
    }

    private function authorizeStaff(): void
    {
        abort_unless(
            auth()->user()?->can('fleet.manage') || auth()->user()?->can('fleet.subscriptions.manage'),
            403
        );
    }

    private function assertClient(int $clientId): void
    {
        abort_unless(DB::table('clients')->where('id', $clientId)->exists(), 404, 'Cliente no encontrado.');
    }
}
