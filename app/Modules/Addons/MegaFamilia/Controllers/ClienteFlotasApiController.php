<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Flotas\Models\FleetDocument;
use App\Modules\Addons\Flotas\Models\FleetGeofence;
use App\Modules\Addons\Flotas\Models\FleetGeofenceEvent;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClienteFlotasApiController extends Controller
{
    private function clientId(): ?int
    {
        return Auth::user()->client_id;
    }

    private function ok(mixed $data): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => $data]);
    }

    private function vehicleForTenant(int $id): FleetVehicle
    {
        $vehicle = FleetVehicle::find($id);
        // 404 siempre — no exponer si el vehículo existe pero es de otro tenant
        if (! $vehicle || (string) $vehicle->client_id !== (string) $this->clientId()) {
            abort(404, 'Vehículo no encontrado.');
        }
        return $vehicle;
    }

    public function tieneFlotas(): JsonResponse
    {
        $cid = $this->clientId();
        if (! $cid) {
            return $this->ok(['tiene_flotas' => false, 'total_vehiculos' => 0]);
        }
        $count = FleetVehicle::where('client_id', $cid)->whereNull('deleted_at')->count();
        return $this->ok(['tiene_flotas' => $count > 0, 'total_vehiculos' => $count]);
    }

    public function vehiculos(): JsonResponse
    {
        $vehicles = FleetVehicle::where('client_id', $this->clientId())
            ->whereNull('deleted_at')
            ->with(['lastPosition', 'device'])
            ->get();

        return $this->ok($vehicles->map(fn ($v) => $this->vehicleToArray($v)));
    }

    public function vehiculoDetalle(int $id): JsonResponse
    {
        $vehicle = $this->vehicleForTenant($id);
        $vehicle->loadMissing(['lastPosition', 'device', 'currentAssignment']);

        return $this->ok($this->vehicleToArray($vehicle));
    }

    public function historialPosiciones(Request $request, int $id): JsonResponse
    {
        $vehicle = $this->vehicleForTenant($id);
        $horas = min(168, max(1, (int) $request->query('horas', 24)));
        $since = Carbon::now()->subHours($horas);

        $positions = \App\Modules\Addons\Flotas\Models\FleetPosition::where('vehicle_id', $vehicle->id)
            ->where('recorded_at', '>=', $since)
            ->orderBy('recorded_at')
            ->get(['lat', 'lng', 'speed', 'heading', 'recorded_at']);

        return $this->ok($positions->map(fn ($p) => [
            'lat'         => (float) $p->lat,
            'lng'         => (float) $p->lng,
            'speed'       => (float) $p->speed,
            'heading'     => (float) $p->heading,
            'recorded_at' => $p->recorded_at?->toIso8601String(),
        ]));
    }

    public function eventosGeocerca(Request $request, int $id): JsonResponse
    {
        $vehicle = $this->vehicleForTenant($id);
        $dias = min(30, max(1, (int) $request->query('dias', 7)));
        $since = Carbon::now()->subDays($dias);

        $events = FleetGeofenceEvent::with('geofence:id,name,color')
            ->where('vehicle_id', $vehicle->id)
            ->where('occurred_at', '>=', $since)
            ->orderByDesc('occurred_at')
            ->limit(100)
            ->get();

        return $this->ok($events->map(fn ($e) => [
            'id'            => $e->id,
            'vehicle_id'    => $e->vehicle_id,
            'event_type'    => $e->event_type,
            'geofence_name' => $e->geofence?->name,
            'geofence_color'=> $e->geofence?->color,
            'occurred_at'   => Carbon::parse($e->occurred_at)->toIso8601String(),
        ]));
    }

    public function documentos(int $id): JsonResponse
    {
        $vehicle = $this->vehicleForTenant($id);
        $docs = $vehicle->documents()->orderBy('expiration_date')->get();

        return $this->ok($docs->map(fn ($d) => [
            'id'              => $d->id,
            'document_type'   => $d->document_type,
            'folio_number'    => $d->folio_number,
            'expiration_date' => $d->expiration_date?->toDateString(),
            'status'          => $d->status,
            'days_until'      => $d->days_until_expiration,
        ]));
    }

    public function mantenimientos(int $id): JsonResponse
    {
        $vehicle = $this->vehicleForTenant($id);
        $items = $vehicle->maintenances()->orderByDesc('service_date')->limit(20)->get();

        return $this->ok($items->map(fn ($m) => [
            'id'               => $m->id,
            'service_type'     => $m->service_type,
            'description'      => $m->description,
            'service_date'     => optional($m->service_date)->toDateString(),
            'next_service_date'=> optional($m->next_service_date)->toDateString(),
        ]));
    }

    public function geocercas(): JsonResponse
    {
        $cid = $this->clientId();
        $vehicleIds = FleetVehicle::where('client_id', $cid)->pluck('id');

        $geofences = FleetGeofence::active()
            ->whereHas('vehicles', fn ($q) => $q->whereIn('fleet_vehicles.id', $vehicleIds))
            ->get(['id', 'name', 'type', 'color', 'polygon']);

        return $this->ok($geofences->map(fn ($g) => [
            'id'      => $g->id,
            'name'    => $g->name,
            'type'    => $g->type,
            'color'   => $g->color,
            'polygon' => $g->polygon,
        ]));
    }

    public function resumen(): JsonResponse
    {
        $cid = $this->clientId();
        $vehicleIds = FleetVehicle::where('client_id', $cid)->pluck('id');

        $activos = FleetVehicle::where('client_id', $cid)
            ->where('status', 'active')
            ->count();

        $docsPorVencer = FleetDocument::whereIn('vehicle_id', $vehicleIds)
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '>=', now())
            ->where('expiration_date', '<=', now()->addDays(30))
            ->count();

        $eventosHoy = FleetGeofenceEvent::whereIn('vehicle_id', $vehicleIds)
            ->whereDate('occurred_at', today())
            ->count();

        $mantProximos = \App\Modules\Addons\Flotas\Models\FleetMaintenance::whereIn('vehicle_id', $vehicleIds)
            ->whereNotNull('next_service_date')
            ->where('next_service_date', '<=', now()->addDays(30))
            ->count();

        return $this->ok([
            'vehiculos_activos'      => $activos,
            'total_vehiculos'        => count($vehicleIds),
            'documentos_por_vencer'  => $docsPorVencer,
            'eventos_geocerca_hoy'   => $eventosHoy,
            'mantenimientos_proximos'=> $mantProximos,
        ]);
    }

    private function vehicleToArray(FleetVehicle $v): array
    {
        $pos = $v->lastPosition;
        return [
            'id'           => $v->id,
            'display_name' => $v->display_name,
            'brand'        => $v->brand,
            'model'        => $v->model,
            'year'         => $v->year,
            'plates'       => $v->plates,
            'color'        => $v->color,
            'status'       => $v->status,
            'has_gps'      => $v->has_gps,
            'position'     => $pos ? [
                'lat'         => (float) $pos->lat,
                'lng'         => (float) $pos->lng,
                'speed'       => (float) $pos->speed,
                'recorded_at' => $pos->recorded_at?->toIso8601String(),
            ] : null,
        ];
    }
}
