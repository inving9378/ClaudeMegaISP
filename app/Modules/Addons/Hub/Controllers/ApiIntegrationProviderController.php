<?php

namespace App\Modules\Addons\Hub\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Core\ApiIntegration;
use App\Models\Core\ApiIntegrationProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ApiIntegrationProviderController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view-integrations')->only(['index']);
        $this->middleware('permission:manage-integrations')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $counts = ApiIntegration::forCompany(1)
            ->selectRaw('provider, count(*) as total')
            ->groupBy('provider')
            ->pluck('total', 'provider');

        $items = ApiIntegrationProvider::orderBy('type')->orderBy('name')->get()
            ->map(fn($p) => $this->format($p, (int) ($counts[$p->slug] ?? 0)));

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'slug'        => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/',
                              Rule::unique('api_integration_providers', 'slug')->whereNull('deleted_at')],
            'name'        => 'required|string|max:150',
            'type'        => 'required|in:ia,servicios',
            'description' => 'nullable|string|max:255',
            'icon'        => 'nullable|string|max:60',
            'docs_url'    => 'nullable|string|max:255',
            'key_format'  => 'nullable|string|max:100',
            'has_config'  => 'boolean',
            'active'      => 'boolean',
        ], ['slug.regex' => 'El identificador solo admite minúsculas, números y guion bajo']);

        if ($v->fails()) {
            return response()->json(['error' => $v->errors()->first()], 422);
        }

        $data = $request->only([
            'slug', 'name', 'type', 'description', 'icon', 'docs_url', 'key_format',
        ]) + [
            'has_config' => $request->boolean('has_config'),
            'active'     => $request->boolean('active', true),
            'is_system'  => false,
        ];

        // Un proveedor borrado conserva su slug (índice único): se revive en vez de chocar
        $trashed = ApiIntegrationProvider::onlyTrashed()->where('slug', $data['slug'])->first();
        if ($trashed) {
            $trashed->restore();
            $trashed->fill($data)->save();
            $provider = $trashed;
        } else {
            $provider = ApiIntegrationProvider::create($data);
        }

        return response()->json(['data' => $this->format($provider, 0)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $provider = ApiIntegrationProvider::findOrFail($id);

        $v = Validator::make($request->all(), [
            'name'        => 'sometimes|string|max:150',
            'type'        => 'sometimes|in:ia,servicios',
            'description' => 'nullable|string|max:255',
            'icon'        => 'nullable|string|max:60',
            'docs_url'    => 'nullable|string|max:255',
            'key_format'  => 'nullable|string|max:100',
            'has_config'  => 'boolean',
            'active'      => 'boolean',
        ]);

        if ($v->fails()) {
            return response()->json(['error' => $v->errors()->first()], 422);
        }

        $provider->fill($request->only([
            'name', 'type', 'description', 'icon', 'docs_url', 'key_format', 'has_config', 'active',
        ]));
        $provider->save();

        // El tipo del proveedor manda: se propaga a sus integraciones existentes
        if ($provider->wasChanged('type')) {
            ApiIntegration::where('provider', $provider->slug)->update(['type' => $provider->type]);
        }

        $count = ApiIntegration::forCompany(1)->where('provider', $provider->slug)->count();

        return response()->json(['data' => $this->format($provider, $count)]);
    }

    public function destroy(int $id): JsonResponse
    {
        $provider = ApiIntegrationProvider::findOrFail($id);

        if ($provider->is_system) {
            return response()->json(['error' => 'Los proveedores del sistema no se pueden eliminar (puedes desactivarlos)'], 422);
        }

        if (ApiIntegration::where('provider', $provider->slug)->exists()) {
            return response()->json(['error' => 'Este proveedor tiene integraciones registradas; elimínalas primero'], 422);
        }

        $provider->delete();

        return response()->json(['message' => 'Eliminado']);
    }

    private function format(ApiIntegrationProvider $p, int $count): array
    {
        return [
            'id'                 => $p->id,
            'slug'               => $p->slug,
            'name'               => $p->name,
            'description'        => $p->description,
            'type'               => $p->type,
            'icon'               => $p->icon,
            'docs_url'           => $p->docs_url,
            'key_format'         => $p->key_format,
            'has_config'         => $p->has_config,
            'is_system'          => $p->is_system,
            'active'             => $p->active,
            'integrations_count' => $count,
        ];
    }
}
