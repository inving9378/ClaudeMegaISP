<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Modules\Addons\Flotas\Models\FleetFuelLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetFuelLogController extends FleetBaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('fleet.view');

        $q = FleetFuelLog::with('vehicle')
            ->forClient($this->clientId())
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

        $this->vehicleForClient($data['vehicle_id']);

        $entry = FleetFuelLog::create($data);

        return response()->json(['fuel_entry' => $entry], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('fleet.fuel.manage');

        FleetFuelLog::forClient($this->clientId())->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }
}
