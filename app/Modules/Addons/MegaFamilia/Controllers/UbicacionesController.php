<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalDevice;
use App\Modules\Addons\MegaFamilia\Models\ParentalLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UbicacionesController extends Controller
{
    public function index()
    {
        return view('addon-megafamilia::ubicaciones.index');
    }

    /**
     * Devuelve la última posición conocida por dispositivo. Útil para el
     * mapa principal sin saturarlo con histórico.
     */
    public function latest(): JsonResponse
    {
        $latestIds = ParentalLocation::query()
            ->select(DB::raw('MAX(id) as id'))
            ->groupBy('device_id')
            ->pluck('id');

        $locations = ParentalLocation::with(['device.profile:id,name'])
            ->whereIn('id', $latestIds)
            ->orderByDesc('recorded_at')
            ->get();

        $offlineDevices = ParentalDevice::with('profile:id,name')
            ->where('status', 'offline')
            ->orderByDesc('last_seen_at')
            ->limit(20)
            ->get();

        return response()->json([
            'locations' => $locations,
            'offline_devices' => $offlineDevices,
        ]);
    }
}
