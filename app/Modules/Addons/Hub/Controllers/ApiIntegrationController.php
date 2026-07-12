<?php

namespace App\Modules\Addons\Hub\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Core\ApiIntegration;
use App\Models\Core\ApiIntegrationLog;
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
            'provider' => 'required|string|max:50',
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

        return response()->json(['data' => $this->formatItem($integration)], 201);
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

        $integration->fill($request->only(['name', 'config', 'active']));

        if ($request->filled('key')) {
            $integration->value = $request->key;
            $this->svc->audit($integration, 'key_updated', [], auth()->user()?->email ?? 'unknown');
        }

        $integration->save();

        return response()->json(['data' => $this->formatItem($integration)]);
    }

    public function destroy(int $id): JsonResponse
    {
        $integration = ApiIntegration::forCompany(1)->findOrFail($id);

        if ($integration->is_default_for_provider) {
            return response()->json(['error' => 'No puedes eliminar la integración predeterminada'], 422);
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

        return response()->json(['message' => 'Key rotada correctamente', 'key_preview' => $integration->key_preview]);
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

    private function formatItem(ApiIntegration $i): array
    {
        return [
            'id'                      => $i->id,
            'provider'                => $i->provider,
            'slug'                    => $i->slug,
            'name'                    => $i->name,
            'key_preview'             => $i->key_preview,
            'has_key'                 => (bool) $i->encrypted_value,
            'config'                  => $i->config,
            'active'                  => $i->active,
            'is_default_for_provider' => $i->is_default_for_provider,
            'last_validation_status'  => $i->last_validation_status,
            'last_validated_at'       => $i->last_validated_at?->toISOString(),
            'created_at'              => $i->created_at?->toISOString(),
        ];
    }
}
