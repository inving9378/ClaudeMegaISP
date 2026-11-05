<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Flotas\Models\FleetGeofence;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Modules\Addons\Flotas\Services\FleetSubscriptionService;
use App\Services\Tenant\CurrentClientResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Geocercas propias del cliente (item #637, Flotas Clientes P4).
 *
 * Reutiliza el modelo/motor de la Fase 3 (#62): `fleet_geofences.client_id` ya es el dueño y
 * GeofenceDetectionService carga las geocercas por vehículo asignado, así que no hay motor paralelo.
 * SEGURIDAD: client_id SIEMPRE de CurrentClientResolver; toda consulta con ->forClient();
 * las del personal (client_id NULL) y las de otro cliente nunca aparecen (404).
 * Los vehículos asignables se filtran a los del propio cliente.
 */
class FlotasGeocercasController extends Controller
{
    private const CIRCLE_SIDES = 32;

    public function __construct(
        private CurrentClientResolver $resolver,
        private FleetSubscriptionService $subs,
    ) {
    }

    public function index(): JsonResponse
    {
        $clientId = $this->gate();

        $items = FleetGeofence::forClient($clientId)->with('vehicles:id')->orderBy('name')->get()
            ->map(fn ($g) => $this->present($g));

        return response()->json([
            'geocercas' => $items,
            'limite'    => $this->limite(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $clientId = $this->gate();
        abort_if(FleetGeofence::forClient($clientId)->count() >= $this->limite(), 422, 'Alcanzaste el máximo de geocercas permitido (' . $this->limite() . ').');

        $data = $this->validated($request);
        $g = FleetGeofence::create([
            'client_id'   => $clientId,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'type'        => $data['type'],
            'polygon'     => $data['polygon'],
            'color'       => $data['color'] ?? '#3b82f6',
            'active'      => $data['active'] ?? true,
        ]);
        $g->vehicles()->sync($this->ownVehicleIds($clientId, $data['vehicle_ids'] ?? []));

        return response()->json(['geocerca' => $this->present($g->load('vehicles:id'))], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $clientId = $this->gate();
        $g = FleetGeofence::forClient($clientId)->findOrFail($id);

        $data = $this->validated($request);
        $g->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'type'        => $data['type'],
            'polygon'     => $data['polygon'],
            'color'       => $data['color'] ?? $g->color,
            'active'      => $data['active'] ?? $g->active,
        ]);
        if (array_key_exists('vehicle_ids', $data)) {
            $g->vehicles()->sync($this->ownVehicleIds($clientId, $data['vehicle_ids'] ?? []));
        }

        return response()->json(['geocerca' => $this->present($g->fresh('vehicles:id'))]);
    }

    public function destroy(int $id): JsonResponse
    {
        $clientId = $this->gate();
        FleetGeofence::forClient($clientId)->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    // ── Internos ─────────────────────────────────────────────────────────────

    private function gate(): int
    {
        $clientId = $this->resolver->resolve();
        abort_unless($clientId, 403, 'Sesión sin cliente.');
        abort_unless($this->subs->clientHasAccess($clientId), 403, 'Tu servicio de Flotas no está activo. Solicita su activación.');

        return (int) $clientId;
    }

    private function limite(): int
    {
        return max(1, (int) config('flotas.portal.max_geocercas', 50));
    }

    /**
     * Acepta polígono ([lat,lng]…) o círculo (center_lat/center_lng/radius_m); el círculo se
     * guarda como polígono de 32 lados para usar el mismo ray casting del motor.
     */
    private function validated(Request $request): array
    {
        $rules = [
            'name'          => 'required|string|max:120',
            'description'   => 'nullable|string|max:500',
            'type'          => 'required|in:enter,exit,both',
            'color'         => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'active'        => 'nullable|boolean',
            'vehicle_ids'   => 'nullable|array',
            'vehicle_ids.*' => 'integer',
            'polygon'       => 'required_without:center_lat|array|min:3|max:200',
            'polygon.*'     => 'array|size:2',
            'polygon.*.*'   => 'numeric',
            'center_lat'    => 'required_without:polygon|numeric|between:-90,90',
            'center_lng'    => 'required_with:center_lat|numeric|between:-180,180',
            'radius_m'      => 'required_with:center_lat|numeric|min:20|max:100000',
        ];
        $data = $request->validate($rules);

        if (isset($data['center_lat'])) {
            $data['polygon'] = $this->circleToPolygon((float) $data['center_lat'], (float) $data['center_lng'], (float) $data['radius_m']);
        }
        $data['polygon'] = array_map(fn ($p) => [(float) $p[0], (float) $p[1]], array_values($data['polygon']));

        return $data;
    }

    private function circleToPolygon(float $lat, float $lng, float $radiusM): array
    {
        $pts = [];
        $dLat = $radiusM / 111320;
        $dLng = $radiusM / (111320 * max(0.01, cos(deg2rad($lat))));
        for ($i = 0; $i < self::CIRCLE_SIDES; $i++) {
            $a = 2 * M_PI * $i / self::CIRCLE_SIDES;
            $pts[] = [round($lat + $dLat * sin($a), 7), round($lng + $dLng * cos($a), 7)];
        }

        return $pts;
    }

    /** Intersecta con las unidades del propio cliente: ids ajenos se descartan en silencio. */
    private function ownVehicleIds(int $clientId, array $ids): array
    {
        return FleetVehicle::forClient($clientId)->whereIn('id', $ids)->pluck('id')->all();
    }

    private function present(FleetGeofence $g): array
    {
        return [
            'id'          => $g->id,
            'name'        => $g->name,
            'description' => $g->description,
            'type'        => $g->type,
            'color'       => $g->color,
            'active'      => (bool) $g->active,
            'polygon'     => $g->polygon,
            'vehicle_ids' => $g->vehicles->pluck('id')->all(),
        ];
    }
}
