<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Modules\Addons\Flotas\Models\FleetMaintenance;
use App\Modules\Addons\Flotas\Models\FleetMaintenanceFile;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Modules\Addons\Flotas\Services\ServicePredictionService;
use Illuminate\Http\JsonResponse;
use App\Modules\Addons\Flotas\Support\FleetVehicleRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FleetMaintenanceController extends FleetBaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->autorizar('fleet.view');

        $q = FleetMaintenance::with(['vehicle', 'provider', 'files'])
            ->forClient($this->clientId())
            ->orderByDesc('service_date');

        if ($request->filled('vehicle_id')) {
            $q->where('vehicle_id', $request->vehicle_id);
        }
        if ($request->filled('type')) {
            $q->where('type', $request->type);
        }

        return response()->json(['maintenances' => $q->get()]);
    }

    public function prediction(int $id, ServicePredictionService $service): JsonResponse
    {
        $this->autorizar('fleet.view');

        $vehicle = FleetVehicle::forClient($this->clientId())->findOrFail($id);

        return response()->json(['predictions' => $service->predict($vehicle)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizar('fleet.maintenance.manage');

        $data = $request->validate(['vehicle_id' => 'required|integer'] + FleetVehicleRules::maintenance());

        $this->vehicleForClient($data['vehicle_id']);

        $maintenance = FleetMaintenance::create($data);

        return response()->json(['maintenance' => $maintenance->load('files', 'provider')], 201);
    }

    public function show(int $id): JsonResponse
    {
        $this->autorizar('fleet.view');

        $m = FleetMaintenance::with(['vehicle', 'provider', 'files'])
            ->forClient($this->clientId())
            ->findOrFail($id);

        return response()->json(['maintenance' => $m]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->autorizar('fleet.maintenance.manage');

        $m = FleetMaintenance::forClient($this->clientId())->findOrFail($id);
        $m->update($request->except(['vehicle_id']));

        return response()->json(['maintenance' => $m->fresh()->load('files', 'provider')]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->autorizar('fleet.maintenance.manage');

        FleetMaintenance::forClient($this->clientId())->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    public function uploadFile(Request $request, int $id): JsonResponse
    {
        $this->autorizar('fleet.maintenance.manage');

        $request->validate(['file' => 'required|file|max:10240']);

        $maintenance = FleetMaintenance::forClient($this->clientId())->findOrFail($id);

        $file   = $request->file('file');
        $path   = Storage::disk('local')->putFile("fleet/maintenance/{$id}", $file);

        $record = FleetMaintenanceFile::create([
            'maintenance_id' => $id,
            'file_path'      => $path,
            'file_name'      => $file->getClientOriginalName(),
            'file_type'      => $file->getMimeType(),
            'file_size'      => $file->getSize(),
        ]);

        return response()->json(['file' => $record], 201);
    }
}
