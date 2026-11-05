<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Modules\Addons\Flotas\Models\FleetFuelLog;
use Illuminate\Http\JsonResponse;
use App\Modules\Addons\Flotas\Support\FleetVehicleRules;
use Illuminate\Http\Request;

class FleetFuelLogController extends FleetBaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->autorizar('fleet.view');

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
        $this->autorizar('fleet.fuel.manage');

        $data = $request->validate(['vehicle_id' => 'required|integer'] + FleetVehicleRules::fuel());

        $this->vehicleForClient($data['vehicle_id']);

        $entry = FleetFuelLog::create($data);

        return response()->json(['fuel_entry' => $entry], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->autorizar('fleet.fuel.manage');

        FleetFuelLog::forClient($this->clientId())->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }
}
