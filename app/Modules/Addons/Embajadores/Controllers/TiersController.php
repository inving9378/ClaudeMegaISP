<?php

namespace App\Modules\Addons\Embajadores\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Referrals\ReferralCommissionTier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TiersController extends Controller
{
    public function index()
    {
        return view('addon-embajadores::tiers.index');
    }

    public function data(): JsonResponse
    {
        return response()->json(ReferralCommissionTier::orderBy('speed_min_mbps')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());
        $tier = ReferralCommissionTier::create($validated);
        return response()->json(['message' => 'Tier creado.', 'tier' => $tier], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tier = ReferralCommissionTier::findOrFail($id);
        $validated = $request->validate($this->rules($id));
        $tier->update($validated);
        return response()->json(['message' => 'Tier actualizado.', 'tier' => $tier]);
    }

    public function toggle(int $id): JsonResponse
    {
        $tier = ReferralCommissionTier::findOrFail($id);
        $tier->update(['active' => !$tier->active]);
        return response()->json(['message' => $tier->active ? 'Tier activado.' : 'Tier desactivado.', 'tier' => $tier]);
    }

    private function rules(?int $ignoreId = null): array
    {
        return [
            'tier_name'      => 'required|string|max:50',
            'speed_min_mbps' => 'required|integer|min:0',
            'speed_max_mbps' => 'required|integer|min:1',
            'level_1_pct'    => 'required|numeric|min:0|max:100',
            'level_2_pct'    => 'required|numeric|min:0|max:100',
            'level_3_pct'    => 'required|numeric|min:0|max:100',
            'level_4_pct'    => 'required|numeric|min:0|max:100',
            'level_5_pct'    => 'required|numeric|min:0|max:100',
            'active'         => 'boolean',
        ];
    }
}
