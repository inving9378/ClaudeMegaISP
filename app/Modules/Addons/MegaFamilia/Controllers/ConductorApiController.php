<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Flotas\Models\FleetAssignment;
use App\Modules\Addons\Flotas\Models\FleetDevice;
use App\Modules\Addons\Flotas\Models\FleetGeofenceEvent;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Modules\Addons\Flotas\Services\FleetPositionService;
use App\Modules\Addons\Flotas\Services\GeofenceDetectionService;
use App\Modules\Addons\Flotas\Services\Gps\Position;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConductorApiController extends Controller
{
    private function resolveVehicle(): FleetVehicle
    {
        $assignment = FleetAssignment::where('user_id', Auth::id())
            ->whereNull('until')
            ->orderByDesc('since')
            ->first();

        abort_unless($assignment, 404, 'No tienes ningún vehículo asignado actualmente.');

        $vehicle = FleetVehicle::find($assignment->vehicle_id);
        abort_unless($vehicle, 404, 'El vehículo asignado ya no existe.');

        return $vehicle;
    }

    private function ok(mixed $data): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => $data]);
    }

    private function err(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => $message], $status);
    }

    public function vehiculo(): JsonResponse
    {
        $vehicle = $this->resolveVehicle();
        $vehicle->loadMissing(['device', 'lastPosition', 'currentAssignment.operator']);

        return $this->ok([
            'id'           => $vehicle->id,
            'display_name' => $vehicle->display_name,
            'brand'        => $vehicle->brand,
            'model'        => $vehicle->model,
            'year'         => $vehicle->year,
            'plates'       => $vehicle->plates,
            'color'        => $vehicle->color,
            'vehicle_type' => $vehicle->vehicle_type,
            'status'       => $vehicle->status,
            'has_gps'      => $vehicle->has_gps,
            'operator'     => $vehicle->currentAssignment?->operator?->name,
        ]);
    }

    public function posicionActual(): JsonResponse
    {
        $vehicle = $this->resolveVehicle();

        $pos = $vehicle->lastPosition;
        if (! $pos) {
            return $this->err('Sin posición GPS registrada para este vehículo.', 404);
        }

        return $this->ok([
            'lat'         => (float) $pos->lat,
            'lng'         => (float) $pos->lng,
            'speed'       => (float) $pos->speed,
            'heading'     => (float) $pos->heading,
            'altitude'    => $pos->altitude ? (float) $pos->altitude : null,
            'recorded_at' => $pos->recorded_at?->toIso8601String(),
        ]);
    }

    public function historialPosiciones(Request $request): JsonResponse
    {
        $horas = min(168, max(1, (int) $request->query('horas', 24)));
        $vehicle = $this->resolveVehicle();

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

    public function geocercas(): JsonResponse
    {
        $vehicle = $this->resolveVehicle();

        $geofences = \App\Modules\Addons\Flotas\Models\FleetGeofence::active()
            ->whereHas('vehicles', fn ($q) => $q->where('fleet_vehicles.id', $vehicle->id))
            ->get(['id', 'name', 'type', 'color', 'polygon']);

        return $this->ok($geofences->map(fn ($g) => [
            'id'      => $g->id,
            'name'    => $g->name,
            'type'    => $g->type,
            'color'   => $g->color,
            'polygon' => $g->polygon,
        ]));
    }

    public function eventosGeocerca(Request $request): JsonResponse
    {
        $dias = min(30, max(1, (int) $request->query('dias', 7)));
        $vehicle = $this->resolveVehicle();

        $since = Carbon::now()->subDays($dias);
        $events = FleetGeofenceEvent::with('geofence:id,name,color')
            ->where('vehicle_id', $vehicle->id)
            ->where('occurred_at', '>=', $since)
            ->orderByDesc('occurred_at')
            ->limit(100)
            ->get();

        return $this->ok($events->map(fn ($e) => [
            'id'            => $e->id,
            'event_type'    => $e->event_type,
            'geofence_name' => $e->geofence?->name,
            'geofence_color'=> $e->geofence?->color,
            'occurred_at'   => Carbon::parse($e->occurred_at)->toIso8601String(),
        ]));
    }

    public function documentos(): JsonResponse
    {
        $vehicle = $this->resolveVehicle();

        $docs = $vehicle->documents()->orderBy('expiration_date')->get();

        return $this->ok($docs->map(fn ($d) => [
            'id'              => $d->id,
            'document_type'   => $d->document_type,
            'folio_number'    => $d->folio_number,
            'issued_by'       => $d->issued_by,
            'issue_date'      => $d->issue_date?->toDateString(),
            'expiration_date' => $d->expiration_date?->toDateString(),
            'status'          => $d->status,
            'days_until'      => $d->days_until_expiration,
            'cost'            => $d->cost,
        ]));
    }

    public function mantenimientos(): JsonResponse
    {
        $vehicle = $this->resolveVehicle();

        $items = $vehicle->maintenances()
            ->with('provider:id,name')
            ->orderByDesc('service_date')
            ->limit(20)
            ->get();

        return $this->ok($items->map(fn ($m) => [
            'id'               => $m->id,
            'service_type'     => $m->service_type,
            'description'      => $m->description,
            'service_date'     => optional($m->service_date)->toDateString(),
            'next_service_date'=> optional($m->next_service_date)->toDateString(),
            'km_at_service'    => $m->km_at_service,
            'next_service_km'  => $m->next_service_km,
            'cost'             => $m->cost,
            'provider'         => $m->provider?->name,
        ]));
    }

    public function reportarPosicion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat'         => 'required|numeric|between:-90,90',
            'lng'         => 'required|numeric|between:-180,180',
            'speed'       => 'nullable|numeric|min:0',
            'heading'     => 'nullable|numeric|between:0,360',
            'accuracy'    => 'nullable|numeric|min:0',
            'recorded_at' => 'nullable|date',
        ]);

        $vehicle = $this->resolveVehicle();

        // Asegurar que el vehículo tiene device
        $device = FleetDevice::where('vehicle_id', $vehicle->id)->first();
        if (! $device) {
            $device = FleetDevice::create([
                'vehicle_id'   => $vehicle->id,
                'imei'         => 'PHONE' . str_pad((string) $vehicle->id, 10, '0', STR_PAD_LEFT),
                'brand'        => 'phone',
                'model'        => 'MegaFamilia App',
                'sim_carrier'  => 'app',
                'status'       => 'active',
                'installed_at' => now(),
            ]);
            $vehicle->update(['has_gps' => true, 'gps_brand' => 'phone', 'gps_imei' => $device->imei]);
        }

        $service = app(FleetPositionService::class);
        $last = $service->getLastPosition($vehicle->id);

        // Anti-jump: descartar si >1 km de la última en <5s
        if ($last && $last->recorded_at) {
            $recordedAt = isset($data['recorded_at'])
                ? Carbon::parse($data['recorded_at'])
                : Carbon::now();

            $secondsDiff = abs($recordedAt->diffInSeconds($last->recorded_at));
            if ($secondsDiff < 5) {
                $dist = $this->haversineKm(
                    (float) $last->lat, (float) $last->lng,
                    (float) $data['lat'], (float) $data['lng']
                );
                if ($dist > 1.0) {
                    return $this->err('Posición descartada: salto GPS detectado (>1 km en <5s).', 422);
                }
            }
        }

        $position = new Position(
            imei: $device->imei,
            lat: (float) $data['lat'],
            lng: (float) $data['lng'],
            speed: (float) ($data['speed'] ?? 0),
            heading: (float) ($data['heading'] ?? 0),
            altitude: null,
            satellites: null,
            hdop: null,
            ignition: true,
            battery: null,
            recorded_at: isset($data['recorded_at']) ? Carbon::parse($data['recorded_at']) : Carbon::now(),
        );

        $service->saveBatch([$position], $vehicle->id, $device->id);

        return $this->ok(['saved' => true]);
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
