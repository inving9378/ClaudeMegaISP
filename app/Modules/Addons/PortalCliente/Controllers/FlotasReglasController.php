<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Flotas\Models\FleetGeofence;
use App\Modules\Addons\Flotas\Models\FleetGeofenceRule;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Modules\Addons\Flotas\Services\FleetSubscriptionService;
use App\Services\Tenant\CurrentClientResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Reglas de alerta propias del cliente (item #644, Flotas Clientes P4c).
 *
 * Reutiliza `fleet_geofence_rules` (sin motor paralelo). Una regla del portal lleva
 * origen='cliente', client_id del resolver y user_id NULL: el panel de Meganet filtra por
 * user_id, así que estas reglas nunca aparecen en /flotas. Toda consulta pasa por ownRules().
 * Unidades y geocercas asignables se intersectan con las del propio cliente.
 */
class FlotasReglasController extends Controller
{
    public const TIPOS = ['geocerca', 'velocidad', 'horario', 'sin_conexion', 'documento', 'mantenimiento'];

    public function __construct(
        private CurrentClientResolver $resolver,
        private FleetSubscriptionService $subs,
    ) {
    }

    /** GET /portal/flotas/reglas: pantalla HTML; con Accept JSON conserva la lista de #644. */
    public function pagina(Request $request)
    {
        if ($request->expectsJson()) {
            return $this->index();
        }
        $this->gate();

        return view('addon-portal-cliente::flotas_reglas', ['cmi' => auth('cliente')->user()]);
    }

    public function index(): JsonResponse
    {
        $clientId = $this->gate();

        return response()->json([
            'reglas' => $this->ownRules($clientId)->orderByDesc('active')->orderBy('name')->get()
                ->map(fn ($r) => $this->present($r)),
            'limite' => $this->limite(),
            'tipos'  => self::TIPOS,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $clientId = $this->gate();
        abort_if($this->ownRules($clientId)->count() >= $this->limite(), 422, 'Alcanzaste el máximo de reglas permitido (' . $this->limite() . ').');

        $rule = FleetGeofenceRule::create($this->payload($request, $clientId) + [
            'client_id' => $clientId,
            'user_id'   => null,
            'origen'    => 'cliente',
        ]);

        return response()->json(['regla' => $this->present($rule)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $clientId = $this->gate();
        $rule = $this->ownRules($clientId)->findOrFail($id);
        $rule->update($this->payload($request, $clientId));

        return response()->json(['regla' => $this->present($rule->fresh())]);
    }

    public function destroy(int $id): JsonResponse
    {
        $clientId = $this->gate();
        $this->ownRules($clientId)->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    public function toggle(int $id): JsonResponse
    {
        $clientId = $this->gate();
        $rule = $this->ownRules($clientId)->findOrFail($id);
        $rule->update(['active' => !$rule->active]);

        return response()->json(['ok' => true, 'active' => (bool) $rule->active]);
    }

    // ── Internos ─────────────────────────────────────────────────────────────

    private function gate(): int
    {
        $clientId = $this->resolver->resolve();
        abort_unless($clientId, 403, 'Sesión sin cliente.');
        abort_unless($this->subs->clientHasAccess($clientId), 403, 'Tu servicio de Flotas no está activo. Solicita su activación.');

        return (int) $clientId;
    }

    private function ownRules(int $clientId)
    {
        return FleetGeofenceRule::where('origen', 'cliente')->where('client_id', $clientId);
    }

    private function limite(): int
    {
        return max(1, (int) config('flotas.portal.max_reglas', 50));
    }

    private function payload(Request $request, int $clientId): array
    {
        $d = $request->validate([
            'name'           => 'required|string|max:150',
            'description'    => 'nullable|string|max:500',
            'tipo'           => ['required', Rule::in(self::TIPOS)],
            'vehicle_ids'    => 'nullable|array',
            'vehicle_ids.*'  => 'integer',
            'geofence_ids'   => 'nullable|array',
            'geofence_ids.*' => 'integer',
            'event_types'    => 'nullable|array',
            'event_types.*'  => ['string', Rule::in(['enter', 'exit'])],
            'time_from'      => 'nullable|date_format:H:i,H:i:s',
            'time_to'        => 'nullable|date_format:H:i,H:i:s|required_with:time_from',
            'days_of_week'   => 'nullable|array',
            'days_of_week.*' => 'integer|min:1|max:7',
            'channels'       => 'required|array|min:1',
            'channels.*'     => ['string', Rule::in(['email', 'whatsapp'])],
            'active'         => 'nullable|boolean',
            'max_kmh'        => 'required_if:tipo,velocidad|nullable|integer|min:10|max:250',
            'minutos'        => 'required_if:tipo,sin_conexion|nullable|integer|min:5|max:10080',
            'dias_antes'     => 'required_if:tipo,documento|nullable|integer|min:1|max:365',
            'km_antes'       => 'nullable|integer|min:50|max:20000',
            'mant_dias'      => 'nullable|integer|min:1|max:365',
        ]);

        $tipo = $d['tipo'];
        if ($tipo === 'geocerca') {
            $request->validate(['event_types' => 'required|array|min:1']);
        }
        if ($tipo === 'horario') {
            $request->validate(['time_from' => 'required', 'time_to' => 'required']);
        }
        if ($tipo === 'mantenimiento' && empty($d['km_antes']) && empty($d['mant_dias'])) {
            abort(422, 'Indica cuántos km o cuántos días antes avisar del mantenimiento.');
        }

        $params = match ($tipo) {
            'velocidad'     => ['max_kmh' => (int) $d['max_kmh']],
            'sin_conexion'  => ['minutos' => (int) $d['minutos']],
            'documento'     => ['dias_antes' => (int) $d['dias_antes']],
            'mantenimiento' => array_filter(['km_antes' => $d['km_antes'] ?? null, 'dias_antes' => $d['mant_dias'] ?? null]),
            default         => [],
        };

        $hasTime = !empty($d['time_from']) && !empty($d['time_to']);

        return [
            'name'         => $d['name'],
            'description'  => $d['description'] ?? null,
            'tipo'         => $tipo,
            'params'       => $params ?: null,
            'vehicle_ids'  => FleetVehicle::forClient($clientId)->whereIn('id', $d['vehicle_ids'] ?? [])->pluck('id')->all(),
            'geofence_ids' => $tipo === 'geocerca'
                ? FleetGeofence::forClient($clientId)->whereIn('id', $d['geofence_ids'] ?? [])->pluck('id')->all()
                : [],
            'event_types'  => $tipo === 'geocerca' ? array_values($d['event_types']) : [],
            'time_from'    => $hasTime ? $d['time_from'] : null,
            'time_to'      => $hasTime ? $d['time_to'] : null,
            'days_of_week' => array_values($d['days_of_week'] ?? []),
            'channels'     => array_values($d['channels']),
            'active'       => $d['active'] ?? true,
        ];
    }

    private function present(FleetGeofenceRule $r): array
    {
        return [
            'id'           => $r->id,
            'name'         => $r->name,
            'description'  => $r->description,
            'tipo'         => $r->tipo,
            'params'       => $r->params ?? [],
            'vehicle_ids'  => $r->vehicle_ids ?? [],
            'geofence_ids' => $r->geofence_ids ?? [],
            'event_types'  => $r->event_types ?? [],
            'time_from'    => $r->time_from ? substr($r->time_from, 0, 5) : null,
            'time_to'      => $r->time_to ? substr($r->time_to, 0, 5) : null,
            'days_of_week' => $r->days_of_week ?? [],
            'channels'     => $r->channels ?? [],
            'active'       => (bool) $r->active,
        ];
    }
}
