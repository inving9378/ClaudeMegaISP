<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Settings globales del módulo (Firebase, Maps, etc).
 * Por ahora se almacenan en cache persistente; cuando haya volumen, migrar a
 * tabla `parental_settings`.
 */
class ConfiguracionController extends Controller
{
    private const KEY = 'megafamilia:settings';

    public function index()
    {
        return view('addon-megafamilia::configuracion.index');
    }

    public function get(): JsonResponse
    {
        return response()->json(Cache::get(self::KEY, $this->defaults()));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'firebase_server_key' => 'nullable|string',
            'firebase_project_id' => 'nullable|string',
            'maps_api_key' => 'nullable|string',
            'fcm_enabled' => 'sometimes|boolean',
            'gps_default_interval' => 'sometimes|integer|min:30',
            'low_battery_threshold' => 'sometimes|integer|min:0|max:100',
        ]);
        $current = Cache::get(self::KEY, $this->defaults());
        Cache::forever(self::KEY, array_merge($current, $data));
        return response()->json(['success' => true]);
    }

    private function defaults(): array
    {
        return [
            'firebase_server_key' => null,
            'firebase_project_id' => null,
            'maps_api_key' => env('MIX_VUE_APP_GOOGLEMAPS_KEY'),
            'fcm_enabled' => false,
            'gps_default_interval' => 300,
            'low_battery_threshold' => 20,
        ];
    }
}
