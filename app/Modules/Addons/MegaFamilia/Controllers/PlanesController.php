<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanesController extends Controller
{
    public function index()
    {
        return view('addon-megafamilia::planes.index');
    }

    public function data(): JsonResponse
    {
        return response()->json(['plans' => ParentalPlan::orderBy('price_monthly')->get()]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $plan = ParentalPlan::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string',
            'price_monthly' => 'sometimes|numeric|min:0',
            'max_children' => 'sometimes|integer|min:0',
            'max_devices' => 'sometimes|integer|min:0',
            'max_parents' => 'sometimes|integer|min:0',
            'features' => 'sometimes|array',
            'active' => 'sometimes|boolean',
        ]);
        $plan->update($data);
        return response()->json(['success' => true, 'plan' => $plan]);
    }
}
