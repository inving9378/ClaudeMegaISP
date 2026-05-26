<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlanesController extends Controller
{
    private const FEATURE_KEYS = [
        'ubicacion', 'geofences', 'reportes_avanzados', 'tareas', 'web_filter',
        'control_pantalla', 'limites_diarios', 'bloqueo_apps', 'bloqueo_web',
        'tareas_recompensas', 'gps', 'mikrotik', 'ia', 'soporte_prioritario',
    ];

    public function index()
    {
        return view('addon-megafamilia::planes.index');
    }

    public function data(): JsonResponse
    {
        $plans = ParentalPlan::query()
            ->withCount('accounts as licenses_count')
            ->orderBy('price_monthly')
            ->get();
        return response()->json(['plans' => $plans]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePlan($request, true);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $plan = ParentalPlan::create($data);
        return response()->json(['success' => true, 'plan' => $plan]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $plan = ParentalPlan::findOrFail($id);
        $data = $this->validatePlan($request, false);
        $plan->update($data);
        return response()->json(['success' => true, 'plan' => $plan]);
    }

    public function destroy(int $id): JsonResponse
    {
        $plan = ParentalPlan::findOrFail($id);
        if ($plan->accounts()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar: el plan tiene cuentas asociadas. Desactívalo en su lugar.',
            ], 422);
        }
        $plan->delete();
        return response()->json(['success' => true]);
    }

    public function toggle(int $id): JsonResponse
    {
        $plan = ParentalPlan::findOrFail($id);
        $plan->update(['active' => ! $plan->active]);
        return response()->json(['success' => true, 'active' => (bool) $plan->active]);
    }

    private function validatePlan(Request $request, bool $isCreate): array
    {
        $rule = $isCreate ? 'required' : 'sometimes';
        return $request->validate([
            'name'          => "{$rule}|string|max:120",
            'slug'          => 'sometimes|nullable|string|max:60',
            'description'   => 'sometimes|nullable|string',
            'price_monthly' => "{$rule}|numeric|min:0",
            'price_yearly'  => 'sometimes|nullable|numeric|min:0',
            'period'        => 'sometimes|in:monthly,yearly',
            'max_children'  => "{$rule}|integer|min:0",
            'max_devices'   => "{$rule}|integer|min:0",
            'max_parents'   => 'sometimes|integer|min:0',
            'features'      => 'sometimes|array',
            'active'        => 'sometimes|boolean',
        ]);
    }
}
