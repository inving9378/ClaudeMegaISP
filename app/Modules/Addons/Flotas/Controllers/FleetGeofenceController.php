<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Modules\Addons\Flotas\Models\FleetGeofence;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// Sub-fase 3.1 — CRUD de geocercas + asignación de vehículos (sin detección automática).
class FleetGeofenceController extends FleetBaseController
{
    // GET /flotas/api/geocercas — lista con filtros (type, active, vehicle_id)
    public function index(Request $request): JsonResponse
    {
        $this->authorize('fleet.geofences.view');

        $q = FleetGeofence::withCount('vehicles')
            ->forClient($this->clientId())
            ->orderByDesc('active')
            ->orderBy('name');

        if ($request->filled('type')) {
            $q->where('type', $request->input('type'));
        }
        if ($request->filled('active') && $request->input('active') !== 'all') {
            $q->where('active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('search')) {
            $s = trim((string) $request->input('search'));
            $q->where('name', 'like', "%{$s}%");
        }
        if ($request->filled('vehicle_id')) {
            $vid = (int) $request->input('vehicle_id');
            $q->whereHas('vehicles', fn($v) => $v->where('fleet_vehicles.id', $vid));
        }

        $geofences = $q->get()->map(fn($g) => $this->present($g));

        return response()->json([
            'geofences' => $geofences,
            'stats'     => [
                'total'             => $geofences->count(),
                'active'            => $geofences->where('active', true)->count(),
                'assigned_vehicles' => $geofences->sum('vehicles_count'),
            ],
        ]);
    }

    // GET /flotas/api/geocercas/{id} — detalle (incluye vehículos asignados)
    public function show(int $id): JsonResponse
    {
        $this->authorize('fleet.geofences.view');

        $geofence = FleetGeofence::with(['vehicles:id,plates,brand,model,year,status'])
            ->withCount('vehicles')
            ->forClient($this->clientId())
            ->findOrFail($id);

        return response()->json([
            'geofence' => array_merge($this->present($geofence), [
                'vehicles' => $geofence->vehicles->map(fn($v) => [
                    'id'           => $v->id,
                    'display_name' => $v->display_name,
                    'plates'       => $v->plates,
                    'status'       => $v->status,
                ])->values(),
            ]),
        ]);
    }

    // POST /flotas/api/geocercas — crear
    public function store(Request $request): JsonResponse
    {
        $this->authorize('fleet.geofences.manage');

        $data = $this->validateData($request);

        $data['client_id'] = $this->clientId();
        $vehicleIds = $this->sanitizeVehicleIds($request->input('vehicle_ids', []));

        $geofence = FleetGeofence::create($data);
        if ($vehicleIds) {
            $geofence->vehicles()->sync($vehicleIds);
        }

        return response()->json([
            'geofence' => $this->present($geofence->loadCount('vehicles')),
        ], 201);
    }

    // PUT/PATCH /flotas/api/geocercas/{id} — actualizar
    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('fleet.geofences.manage');

        $geofence = FleetGeofence::forClient($this->clientId())->findOrFail($id);
        $data = $this->validateData($request);
        $geofence->update($data);

        // Si vienen vehículos en el payload, sincroniza también.
        if ($request->has('vehicle_ids')) {
            $geofence->vehicles()->sync($this->sanitizeVehicleIds($request->input('vehicle_ids', [])));
        }

        return response()->json([
            'geofence' => $this->present($geofence->loadCount('vehicles')),
        ]);
    }

    // POST /flotas/api/geocercas/{id}/vehiculos — actualizar solo el pivot de vehículos
    public function assignVehicles(Request $request, int $id): JsonResponse
    {
        $this->authorize('fleet.geofences.manage');

        $geofence = FleetGeofence::forClient($this->clientId())->findOrFail($id);
        $vehicleIds = $this->sanitizeVehicleIds($request->input('vehicle_ids', []));
        $geofence->vehicles()->sync($vehicleIds);

        return response()->json([
            'ok'             => true,
            'vehicles_count' => count($vehicleIds),
        ]);
    }

    // DELETE /flotas/api/geocercas/{id} — soft delete
    public function destroy(int $id): JsonResponse
    {
        $this->authorize('fleet.geofences.manage');

        $geofence = FleetGeofence::forClient($this->clientId())->findOrFail($id);
        $geofence->delete();

        return response()->json(['ok' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'name'        => 'required|string|max:150',
            'description' => 'nullable|string',
            'type'        => ['required', Rule::in(['enter', 'exit', 'both'])],
            'polygon'     => 'required|array|min:3',
            'polygon.*'   => 'array|size:2',
            'polygon.*.*' => 'numeric',
            'color'       => 'nullable|string|max:9',
            'active'      => 'boolean',
        ], [
            'polygon.min' => 'El polígono debe tener al menos 3 puntos.',
        ]);

        $data['color'] = $data['color'] ?? '#3388ff';
        $data['active'] = $request->boolean('active');

        return $data;
    }

    /** Solo permite asignar vehículos que pertenezcan al mismo tenant. */
    private function sanitizeVehicleIds($raw): array
    {
        $ids = collect(is_array($raw) ? $raw : [])
            ->map(fn($v) => (int) $v)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return FleetVehicle::forClient($this->clientId())
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();
    }

    private function present(FleetGeofence $g): array
    {
        return [
            'id'             => $g->id,
            'client_id'      => $g->client_id,
            'name'           => $g->name,
            'description'    => $g->description,
            'type'           => $g->type,
            'polygon'        => $g->polygon,
            'color'          => $g->color,
            'active'         => (bool) $g->active,
            'centroid'       => $g->centroid,
            'vehicles_count' => $g->vehicles_count ?? 0,
            'created_at'     => $g->created_at?->toIso8601String(),
            'updated_at'     => $g->updated_at?->toIso8601String(),
        ];
    }

}
