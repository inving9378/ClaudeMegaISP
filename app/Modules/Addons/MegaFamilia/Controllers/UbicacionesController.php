<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalDevice;
use App\Modules\Addons\MegaFamilia\Models\ParentalLocation;
use App\Modules\Addons\MegaFamilia\Models\ParentalProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UbicacionesController extends Controller
{
    public function index()
    {
        return view('addon-megafamilia::ubicaciones.index');
    }

    /**
     * Última posición conocida por dispositivo (un punto por device_id).
     */
    public function latest(): JsonResponse
    {
        $latestIds = ParentalLocation::query()
            ->select(DB::raw('MAX(id) as id'))
            ->groupBy('device_id')
            ->pluck('id');

        $locations = ParentalLocation::with(['device.profile:id,name,profile_type,photo'])
            ->whereIn('id', $latestIds)
            ->orderByDesc('recorded_at')
            ->get();

        $offlineDevices = ParentalDevice::with('profile:id,name')
            ->where('status', 'offline')
            ->orderByDesc('last_seen_at')
            ->limit(20)
            ->get();

        return response()->json([
            'locations'       => $locations,
            'offline_devices' => $offlineDevices,
        ]);
    }

    /**
     * Lista de perfiles activos con su última ubicación y batería del
     * dispositivo principal. Estructura compacta para el panel lateral.
     */
    public function profiles(): JsonResponse
    {
        $profiles = ParentalProfile::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'account_id', 'name', 'photo', 'profile_type', 'age']);

        $devices = ParentalDevice::query()
            ->whereIn('profile_id', $profiles->pluck('id'))
            ->get(['id', 'profile_id', 'name', 'os', 'battery_level', 'last_seen_at', 'status']);

        $devicesByProfile = $devices->groupBy('profile_id');

        $latestIds = ParentalLocation::query()
            ->whereIn('device_id', $devices->pluck('id'))
            ->select(DB::raw('MAX(id) as id'))
            ->groupBy('device_id')
            ->pluck('id');

        $latestLocations = ParentalLocation::whereIn('id', $latestIds)
            ->get()
            ->keyBy('device_id');

        $result = $profiles->map(function ($p) use ($devicesByProfile, $latestLocations) {
            $devs = $devicesByProfile->get($p->id, collect());

            $bestLoc = null;
            $bestDev = null;
            foreach ($devs as $d) {
                $loc = $latestLocations->get($d->id);
                if (!$loc) continue;
                if (!$bestLoc || $loc->recorded_at > $bestLoc->recorded_at) {
                    $bestLoc = $loc;
                    $bestDev = $d;
                }
            }

            return [
                'id'           => $p->id,
                'name'         => $p->name,
                'photo'        => $p->photo,
                'profile_type' => $p->profile_type,
                'age'          => $p->age,
                'devices_count'=> $devs->count(),
                'device'       => $bestDev,
                'last_location'=> $bestLoc,
                'battery_level'=> $bestDev?->battery_level,
                'last_seen_at' => $bestDev?->last_seen_at,
            ];
        });

        return response()->json(['profiles' => $result->values()]);
    }

    /**
     * Últimas 50 ubicaciones del perfil (a través de cualquiera de sus devices),
     * ordenadas ascendentes para dibujar polyline cronológica.
     */
    public function history(int $profileId): JsonResponse
    {
        $deviceIds = ParentalDevice::where('profile_id', $profileId)->pluck('id');

        $history = ParentalLocation::whereIn('device_id', $deviceIds)
            ->orderByDesc('recorded_at')
            ->limit(50)
            ->get(['id', 'device_id', 'lat', 'lng', 'accuracy', 'battery', 'recorded_at'])
            ->reverse()
            ->values();

        return response()->json(['history' => $history]);
    }
}
