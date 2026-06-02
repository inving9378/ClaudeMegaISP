<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Flotas\Models\FleetFuelLog;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetFuelLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('fleet.view');

        $q = FleetFuelLog::with('vehicle')
            ->whereHas('vehicle', fn($v) => $this->scopeClient($v))
            ->orderByDesc('refuel_date');

        if ($request->filled('vehicle_id')) {
            $q->where('vehicle_id', $request->vehicle_id);
        }

        return response()->json([
            'fuel_log' => $q->get()->map(fn($f) => array_merge($f->toArray(), [
                'cost_per_liter' => $f->cost_per_liter,
            ])),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('fleet.fuel.manage');

        $data = $request->validate([
            'vehicle_id'   => 'required|integer',
            'refuel_date'  => 'required|date',
            'liters'       => 'required|numeric|min:0.1',
            'cost'         => 'required|numeric|min:0',
            'km_at_refuel' => 'nullable|integer|min:0',
            'octane'       => 'nullable|string|max:10',
            'station_name' => 'nullable|string|max:150',
        ]);

        FleetVehicle::where(fn($q) => $this->scopeClient($q))->findOrFail($data['vehicle_id']);

        $entry = FleetFuelLog::create($data);

        return response()->json(['fuel_entry' => $entry], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('fleet.fuel.manage');

        FleetFuelLog::whereHas('vehicle', fn($v) => $this->scopeClient($v))->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    private function scopeClient($q)
    {
        $user = auth()->user();
        if ($user->hasRole(['super-administrator', 'DESARROLLADOR'])) {
            return $q->whereNull('client_id');
        }
        return $q->where('client_id', $user->client_id ?? 0);
    }
}
