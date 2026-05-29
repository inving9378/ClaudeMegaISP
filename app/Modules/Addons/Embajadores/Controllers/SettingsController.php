<?php

namespace App\Modules\Addons\Embajadores\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Referrals\ReferralSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        return view('addon-embajadores::configuracion.index');
    }

    public function get(): JsonResponse
    {
        return response()->json(ReferralSetting::current());
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'program_active'         => 'required|boolean',
            'threshold_amount'       => 'required|numeric|min:1',
            'duration_months'        => 'required|integer|min:1|max:24',
            'max_levels'             => 'required|integer|min:1|max:5',
            'program_name'           => 'required|string|max:100',
            'welcome_message'        => 'nullable|string',
            'share_template_default' => 'nullable|string',
            'terms_conditions'       => 'nullable|string',
        ]);

        $settings = ReferralSetting::current();
        $settings->update($validated);

        return response()->json(['message' => 'Configuración guardada correctamente.', 'settings' => $settings]);
    }
}
