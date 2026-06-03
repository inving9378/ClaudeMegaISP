<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Flotas\Models\FleetDevice;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Modules\Addons\Flotas\Services\FleetPositionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetGpsController extends Controller
{
    public function __construct(private FleetPositionService $positions)
    {
    }

    // GET /flotas/api/gps/flota — última posición de todos los vehículos con GPS (mapa global)
    public function fleet(): JsonResponse
    {
        $this->authorize('fleet.gps.view');

        return response()->json([
            'vehicles' => $this->positions->getCurrentPositions($this->clientId())->values(),
        ]);
    }

    // GET /flotas/api/vehiculos/{id}/gps — estado actual + dispositivo + estadísticas de hoy
    public function status(int $id): JsonResponse
    {
        $this->authorize('fleet.gps.view');

        $vehicle = FleetVehicle::forClient($this->clientId())->findOrFail($id);
        $device  = FleetDevice::where('vehicle_id', $vehicle->id)->first();
        $last    = $this->positions->getLastPosition($vehicle->id);

        return response()->json([
            'has_gps'     => (bool) $vehicle->has_gps,
            'device'      => $device,
            'last'        => $last,
            'live_status' => $this->positions->liveStatus($last),
            'stats_today' => $this->statsForToday($vehicle->id),
        ]);
    }

    // GET /flotas/api/vehiculos/{id}/gps/history?from=&to=
    public function history(Request $request, int $id): JsonResponse
    {
        $this->authorize('fleet.gps.view');

        $vehicle = FleetVehicle::forClient($this->clientId())->findOrFail($id);

        $to   = $request->filled('to') ? Carbon::parse($request->input('to')) : Carbon::now();
        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : (clone $to)->subDay();

        $positions = $this->positions->getHistory($vehicle->id, $from, $to)
            ->map(fn($p) => [
                'lat'         => (float) $p->lat,
                'lng'         => (float) $p->lng,
                'speed'       => (float) $p->speed,
                'heading'     => (float) $p->heading,
                'recorded_at' => $p->recorded_at?->toIso8601String(),
            ]);

        return response()->json([
            'from'      => $from->toIso8601String(),
            'to'        => $to->toIso8601String(),
            'count'     => $positions->count(),
            'positions' => $positions->values(),
        ]);
    }

    // POST /flotas/api/vehiculos/{id}/gps/device — activa tracking registrando el dispositivo
    public function activateDevice(Request $request, int $id): JsonResponse
    {
        $this->authorize('fleet.gps.manage');

        $vehicle = FleetVehicle::forClient($this->clientId())->findOrFail($id);

        $data = $request->validate([
            'brand'       => 'required|in:ruptela,concox,gt06_generic,mock',
            'model'       => 'nullable|string|max:100',
            'imei'        => 'required|string|max:20|unique:fleet_devices,imei',
            'sim_number'  => 'nullable|string|max:20',
            'sim_carrier' => 'nullable|string|max:50',
        ]);

        // Un dispositivo físico (no mock) arranca en 'pending_first_connection':
        // el listener TCP lo pasará a 'active' cuando se conecte por primera vez (Sub-fase 2.3a).
        $isMock = $data['brand'] === 'mock';

        $device = FleetDevice::create([
            'vehicle_id'   => $vehicle->id,
            'imei'         => $data['imei'],
            'brand'        => $data['brand'],
            'model'        => $data['model'] ?? null,
            'sim_number'   => $data['sim_number'] ?? null,
            'sim_carrier'  => $data['sim_carrier'] ?? null,
            'status'       => $isMock ? 'active' : 'pending_first_connection',
            'installed_at' => now(),
        ]);

        $vehicle->update([
            'has_gps'     => true,
            'gps_brand'   => $data['brand'],
            'gps_model'   => $data['model'] ?? null,
            'gps_imei'    => $data['imei'],
            'gps_sim'     => $data['sim_number'] ?? null,
            'gps_carrier' => $data['sim_carrier'] ?? null,
        ]);

        // Instrucciones para apuntar el dispositivo físico al listener TCP.
        $listener = $isMock ? null : [
            'ip'   => env('GPS_LISTENER_PUBLIC_IP', '[IP pública del servidor]'),
            'port' => 5027,
            'protocol' => 'TCP',
        ];

        return response()->json(['ok' => true, 'device' => $device, 'listener' => $listener], 201);
    }

    // GET /flotas/api/vehiculos/{id}/geofence-events?limit=20 — últimos eventos de geocercas
    public function geofenceEvents(Request $request, int $id): JsonResponse
    {
        $this->authorize('fleet.geofences.view');

        $vehicle = FleetVehicle::forClient($this->clientId())->findOrFail($id);
        $limit = min(100, max(1, (int) $request->input('limit', 20)));

        $events = \App\Modules\Addons\Flotas\Models\FleetGeofenceEvent::with([
                'geofence:id,name,color',
                'position:id,lat,lng',
            ])
            ->where('vehicle_id', $vehicle->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn($e) => [
                'id'            => $e->id,
                'type'          => $e->event_type, // 'enter' | 'exit'
                'geofence_id'   => $e->geofence_id,
                'geofence_name' => $e->geofence?->name ?? 'Geocerca eliminada',
                'color'         => $e->geofence?->color,
                'lat'           => $e->position ? (float) $e->position->lat : null,
                'lng'           => $e->position ? (float) $e->position->lng : null,
                'occurred_at'   => $e->occurred_at?->toIso8601String(),
            ]);

        return response()->json(['events' => $events]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Estadísticas del día: km recorridos, minutos en movimiento y número de paradas. */
    private function statsForToday(int $vehicleId): array
    {
        $points = $this->positions->getHistory($vehicleId, Carbon::today(), Carbon::now());

        $km = 0.0;
        $movingSeconds = 0;
        $stops = 0;
        $prev = null;
        $wasMoving = false;

        foreach ($points as $p) {
            if ($prev) {
                $km += $this->haversine($prev->lat, $prev->lng, $p->lat, $p->lng);
                $gap = $prev->recorded_at->diffInSeconds($p->recorded_at);
                if ($p->speed > 3) {
                    $movingSeconds += $gap;
                }
            }
            $moving = $p->speed > 3;
            if ($wasMoving && !$moving) {
                $stops++;
            }
            $wasMoving = $moving;
            $prev = $p;
        }

        return [
            'km'            => round($km, 1),
            'moving_minutes' => (int) round($movingSeconds / 60),
            'stops'         => $stops,
            'pings'         => $points->count(),
        ];
    }

    /** Distancia en km entre dos coordenadas (Haversine). */
    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function clientId(): ?int
    {
        $user = auth()->user();
        if ($user->hasRole(['super-administrator', 'DESARROLLADOR'])) {
            return null; // ve flota interna Meganet
        }
        return $user->client_id ?? null;
    }
}
