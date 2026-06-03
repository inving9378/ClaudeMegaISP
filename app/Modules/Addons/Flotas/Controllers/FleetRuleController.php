<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Modules\Addons\Flotas\Models\FleetGeofenceRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// Sub-fase 3.4 — Reglas de alerta del usuario (capa opt-in sobre 3.3).
// Multi-tenancy estricto: cada usuario solo ve/gestiona SUS propias reglas.
class FleetRuleController extends FleetBaseController
{
    // GET /flotas/api/reglas — reglas del usuario actual
    public function index(): JsonResponse
    {
        $this->authorize('fleet.rules.view');

        $rules = FleetGeofenceRule::where('user_id', auth()->id())
            ->orderByDesc('active')->orderBy('name')
            ->get()
            ->map(fn ($r) => $this->present($r));

        return response()->json(['rules' => $rules]);
    }

    // POST /flotas/api/reglas
    public function store(Request $request): JsonResponse
    {
        $this->authorize('fleet.rules.manage');

        $data = $this->validateData($request);
        $data['user_id']   = auth()->id();
        $data['client_id'] = $this->clientId();

        $rule = FleetGeofenceRule::create($data);
        return response()->json(['rule' => $this->present($rule)], 201);
    }

    // PUT /flotas/api/reglas/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('fleet.rules.manage');

        $rule = FleetGeofenceRule::where('user_id', auth()->id())->findOrFail($id);
        $rule->update($this->validateData($request));
        return response()->json(['rule' => $this->present($rule)]);
    }

    // DELETE /flotas/api/reglas/{id}
    public function destroy(int $id): JsonResponse
    {
        $this->authorize('fleet.rules.manage');

        $rule = FleetGeofenceRule::where('user_id', auth()->id())->findOrFail($id);
        $rule->delete();
        return response()->json(['ok' => true]);
    }

    // POST /flotas/api/reglas/{id}/toggle
    public function toggle(int $id): JsonResponse
    {
        $this->authorize('fleet.rules.manage');

        $rule = FleetGeofenceRule::where('user_id', auth()->id())->findOrFail($id);
        $rule->update(['active' => !$rule->active]);
        return response()->json(['ok' => true, 'active' => $rule->active]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'name'           => 'required|string|max:150',
            'description'    => 'nullable|string',
            'vehicle_ids'    => 'array',
            'vehicle_ids.*'  => 'integer',
            'geofence_ids'   => 'array',
            'geofence_ids.*' => 'integer',
            'event_types'    => 'required|array|min:1',
            'event_types.*'  => ['string', Rule::in(['enter', 'exit'])],
            'time_from'      => 'nullable|date_format:H:i,H:i:s',
            'time_to'        => 'nullable|date_format:H:i,H:i:s|required_with:time_from',
            'days_of_week'   => 'array',
            'days_of_week.*' => 'integer|min:1|max:7',
            'channels'       => 'required|array|min:1',
            'channels.*'     => ['string', Rule::in(['email', 'whatsapp'])],
            'active'         => 'boolean',
        ]);

        // Normaliza arreglos opcionales a [] (= "todos") y limpia time si falta uno.
        $data['vehicle_ids']  = array_values($data['vehicle_ids'] ?? []);
        $data['geofence_ids'] = array_values($data['geofence_ids'] ?? []);
        $data['days_of_week'] = array_values($data['days_of_week'] ?? []);
        $data['event_types']  = array_values($data['event_types']);
        $data['channels']     = array_values($data['channels']);
        $data['active']       = $request->boolean('active', true);

        if (empty($data['time_from']) || empty($data['time_to'])) {
            $data['time_from'] = null;
            $data['time_to']   = null;
        }

        return $data;
    }

    private function present(FleetGeofenceRule $r): array
    {
        return [
            'id'           => $r->id,
            'name'         => $r->name,
            'description'  => $r->description,
            'vehicle_ids'  => $r->vehicle_ids ?? [],
            'geofence_ids' => $r->geofence_ids ?? [],
            'event_types'  => $r->event_types ?? [],
            'time_from'    => $r->time_from ? substr($r->time_from, 0, 5) : null, // HH:MM
            'time_to'      => $r->time_to ? substr($r->time_to, 0, 5) : null,
            'days_of_week' => $r->days_of_week ?? [],
            'channels'     => $r->channels ?? [],
            'active'       => (bool) $r->active,
        ];
    }

}
