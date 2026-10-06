<?php

namespace App\Modules\Addons\Hub\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Core\ApiIntegration;
use App\Models\Core\ApiIntegrationLog;
use App\Modules\Addons\Hub\Services\Servicios;
use App\Modules\Addons\IA\Services\IA;
use App\Services\Core\ApiIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApiIntegrationController extends Controller
{
    public function __construct(private ApiIntegrationService $svc)
    {
        $this->middleware('permission:view-integrations')->only(['index', 'show', 'logs', 'usage', 'providers']);
        $this->middleware('permission:manage-integrations')->only(['store', 'update', 'setDefault', 'destroy']);
        $this->middleware('permission:rotate-integration-keys')->only('rotate');
        $this->middleware('permission:view-integration-logs')->only(['logs', 'usage']);
        $this->middleware('permission:validate-integrations')->only('validateKey');
    }

    public function providers(): JsonResponse
    {
        return response()->json(['data' => $this->svc->getProviders()]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = ApiIntegration::forCompany(1);

        if ($request->filled('provider')) {
            $query->where('provider', $request->provider);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $items = $query->orderBy('provider')->orderByDesc('is_default_for_provider')->get()
            ->makeHidden(['encrypted_value'])
            ->map(fn($i) => $this->formatItem($i));

        return response()->json(['data' => $items]);
    }

    public function show(int $id): JsonResponse
    {
        $item = ApiIntegration::forCompany(1)->findOrFail($id);
        return response()->json(['data' => $this->formatItem($item)]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'provider' => 'required|string|max:50|exists:api_integration_providers,slug,deleted_at,NULL,active,1',
            'slug'     => 'required|string|max:100|unique:api_integrations,slug,NULL,id,company_id,1',
            'name'     => 'required|string|max:150',
            'key'      => 'nullable|string',
            'config'   => 'nullable|array',
            'active'   => 'boolean',
        ]);

        if ($v->fails()) {
            return response()->json(['error' => $v->errors()->first()], 422);
        }

        $integration = ApiIntegration::create([
            'company_id'             => 1,
            'provider'               => $request->provider,
            'slug'                   => $request->slug,
            'name'                   => $request->name,
            'config'                 => $request->config ?? [],
            'active'                 => $request->boolean('active', true),
            'is_default_for_provider'=> false,
        ]);

        if ($request->filled('key')) {
            $integration->value = $request->key;
            $integration->save();
        }

        $this->svc->audit($integration, 'key_created', [], auth()->user()?->email ?? 'unknown');

        $validacion = $this->validarSiAplica($integration);

        return response()->json(['data' => $this->formatItem($integration->fresh()), 'validacion' => $validacion], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $integration = ApiIntegration::forCompany(1)->findOrFail($id);

        $v = Validator::make($request->all(), [
            'name'   => 'sometimes|string|max:150',
            'key'    => 'nullable|string',
            'config' => 'nullable|array',
            'active' => 'boolean',
        ]);

        if ($v->fails()) {
            return response()->json(['error' => $v->errors()->first()], 422);
        }

        $llaveCambio  = $request->filled('key');
        $sinVacios    = fn($c) => array_filter((array) $c, fn($v) => $v !== null && $v !== '');
        $configCambio = $request->has('config') && $sinVacios($request->input('config')) != $sinVacios($integration->config);
        $seDesactiva  = $integration->active && $request->has('active') && !$request->boolean('active');

        $integration->fill($request->only(['name', 'config', 'active']));

        if ($llaveCambio) {
            $integration->value = $request->key;
            $this->svc->audit($integration, 'key_updated', [], auth()->user()?->email ?? 'unknown');
        }

        // Llave o URL nuevas: el "Válida" anterior ya no dice nada de la configuración actual.
        if ($llaveCambio || $configCambio) {
            $integration->last_validation_status = null;
            $integration->last_validated_at = null;
        }

        $integration->save();

        $validacion = ($llaveCambio || $configCambio) ? $this->validarSiAplica($integration) : null;

        $aviso = null;
        if ($seDesactiva && $usos = IA::usosDeIntegracion($integration)) {
            $aviso = 'Integración desactivada. Estos módulos se quedan sin IA hasta que les asignes otra: ' . implode(', ', $usos) . '.';
        } elseif ($seDesactiva && $usos = Servicios::usosDeIntegracion($integration)) {
            $aviso = 'Integración desactivada. Estos módulos vuelven a usar lo de siempre hasta que les asignes otra: ' . implode(', ', $usos) . '.';
        }

        return response()->json([
            'data'       => $this->formatItem($integration->fresh()),
            'validacion' => $validacion,
            'aviso'      => $aviso,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $integration = ApiIntegration::forCompany(1)->findOrFail($id);

        if ($integration->is_default_for_provider) {
            return response()->json(['error' => 'No puedes eliminar la integración predeterminada'], 422);
        }

        // Borrarla dejaría sin IA a los módulos que la usan: primero hay que moverlos.
        if ($usos = $this->usos($integration)) {
            return response()->json([
                'error' => 'No se puede eliminar: la usan ' . implode(', ', $usos) . '. Asígnales otra en '
                    . ($integration->type === 'ia' ? 'Módulos IA (o en /ia/configuracion)' : 'Módulos de servicios') . ' y vuelve a intentar.',
            ], 422);
        }

        $this->svc->audit($integration, 'key_deleted', [], auth()->user()?->email ?? 'unknown');
        $integration->delete();

        return response()->json(['message' => 'Eliminada']);
    }

    public function validateKey(int $id): JsonResponse
    {
        $integration = ApiIntegration::forCompany(1)->findOrFail($id);
        $result = $this->svc->validate($integration);
        return response()->json($result);
    }

    public function setDefault(int $id): JsonResponse
    {
        $integration = ApiIntegration::forCompany(1)->findOrFail($id);

        // Demote current default
        ApiIntegration::forCompany(1)
            ->where('provider', $integration->provider)
            ->where('is_default_for_provider', true)
            ->update(['is_default_for_provider' => false]);

        $integration->update(['is_default_for_provider' => true, 'active' => true]);
        $this->svc->audit($integration, 'set_as_default', [], auth()->user()?->email ?? 'unknown');

        return response()->json(['message' => 'Integración marcada como predeterminada']);
    }

    public function rotate(Request $request, int $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'new_key' => 'required|string|min:8',
        ]);

        if ($v->fails()) {
            return response()->json(['error' => $v->errors()->first()], 422);
        }

        $integration = ApiIntegration::forCompany(1)->findOrFail($id);

        $integration->value = $request->new_key;
        $integration->last_validation_status = null;
        $integration->last_validated_at = null;
        $integration->save();

        $this->svc->audit($integration, 'key_rotated', [], auth()->user()?->email ?? 'unknown');

        return response()->json([
            'message'     => 'Key rotada correctamente',
            'key_preview' => $integration->key_preview,
            'validacion'  => $this->validarSiAplica($integration),
        ]);
    }

    public function logs(int $id): JsonResponse
    {
        $integration = ApiIntegration::forCompany(1)->findOrFail($id);

        $logs = ApiIntegrationLog::where('integration_id', $id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $logs]);
    }

    public function usage(int $id): JsonResponse
    {
        $integration = ApiIntegration::forCompany(1)->findOrFail($id);

        $usage = $integration->usage()
            ->orderByDesc('usage_date')
            ->limit(30)
            ->get();

        return response()->json(['data' => $usage]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    /** Módulos (de IA o de servicios) que dependen de esta integración. */
    private function usos(ApiIntegration $i): array
    {
        return $i->type === 'ia' ? IA::usosDeIntegracion($i) : Servicios::usosDeIntegracion($i);
    }

    /**
     * Al guardar una llave nueva se prueba de inmediato, para que quien la cambia
     * sepa en el momento si funciona (y por qué no). Null si no hay cómo probarla.
     */
    private function validarSiAplica(ApiIntegration $integration): ?array
    {
        if (!$integration->active || !$this->svc->puedeValidar($integration)) {
            return null;
        }
        return $this->svc->validate($integration);
    }

    private function formatItem(ApiIntegration $i): array
    {
        return [
            'id'                      => $i->id,
            'provider'                => $i->provider,
            'type'                    => $i->type,
            'slug'                    => $i->slug,
            'name'                    => $i->name,
            'key_preview'             => $i->key_preview,
            'has_key'                 => (bool) $i->encrypted_value,
            'config'                  => $i->config,
            'active'                  => $i->active,
            'is_default_for_provider' => $i->is_default_for_provider,
            'last_validation_status'  => $i->last_validation_status,
            'last_validated_at'       => $i->last_validated_at?->toISOString(),
            'last_validation_message' => $i->last_validation_status ? $this->svc->ultimoMensajeValidacion($i) : null,
            'en_uso'                  => $this->usos($i),
            'created_at'              => $i->created_at?->toISOString(),
        ];
    }
}
