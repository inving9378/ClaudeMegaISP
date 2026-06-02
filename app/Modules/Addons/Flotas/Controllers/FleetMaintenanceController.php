<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Flotas\Models\FleetMaintenance;
use App\Modules\Addons\Flotas\Models\FleetMaintenanceFile;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FleetMaintenanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('fleet.view');

        $q = FleetMaintenance::with(['vehicle', 'provider', 'files'])
            ->whereHas('vehicle', fn($v) => $this->scopeClient($v))
            ->orderByDesc('service_date');

        if ($request->filled('vehicle_id')) {
            $q->where('vehicle_id', $request->vehicle_id);
        }
        if ($request->filled('type')) {
            $q->where('type', $request->type);
        }

        return response()->json(['maintenances' => $q->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('fleet.maintenance.manage');

        $data = $request->validate([
            'vehicle_id'       => 'required|integer',
            'type'             => 'required|in:preventive,corrective,emergency,verification',
            'service_date'     => 'required|date',
            'service_km'       => 'nullable|integer|min:0',
            'works'            => 'nullable|array',
            'works.*'          => 'string',
            'description'      => 'nullable|string',
            'provider_id'      => 'nullable|integer',
            'mechanic_name'    => 'nullable|string|max:150',
            'labor_cost'       => 'nullable|numeric|min:0',
            'parts_cost'       => 'nullable|numeric|min:0',
            'other_cost'       => 'nullable|numeric|min:0',
            'next_service_date'=> 'nullable|date',
            'next_service_km'  => 'nullable|integer|min:0',
            'is_draft'         => 'boolean',
        ]);

        // Validar que el vehículo pertenece al cliente del usuario
        FleetVehicle::where(fn($q) => $this->scopeClient($q))->findOrFail($data['vehicle_id']);

        $maintenance = FleetMaintenance::create($data);

        return response()->json(['maintenance' => $maintenance->load('files', 'provider')], 201);
    }

    public function show(int $id): JsonResponse
    {
        $this->authorize('fleet.view');

        $m = FleetMaintenance::with(['vehicle', 'provider', 'files'])
            ->whereHas('vehicle', fn($v) => $this->scopeClient($v))
            ->findOrFail($id);

        return response()->json(['maintenance' => $m]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('fleet.maintenance.manage');

        $m = FleetMaintenance::whereHas('vehicle', fn($v) => $this->scopeClient($v))->findOrFail($id);
        $m->update($request->except(['vehicle_id']));

        return response()->json(['maintenance' => $m->fresh()->load('files', 'provider')]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('fleet.maintenance.manage');

        FleetMaintenance::whereHas('vehicle', fn($v) => $this->scopeClient($v))->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    public function uploadFile(Request $request, int $id): JsonResponse
    {
        $this->authorize('fleet.maintenance.manage');

        $request->validate(['file' => 'required|file|max:10240']); // 10MB

        $maintenance = FleetMaintenance::whereHas('vehicle', fn($v) => $this->scopeClient($v))->findOrFail($id);

        $file = $request->file('file');
        $path = Storage::disk('local')->putFile("fleet/maintenance/{$id}", $file);

        $record = FleetMaintenanceFile::create([
            'maintenance_id' => $id,
            'file_path'      => $path,
            'file_name'      => $file->getClientOriginalName(),
            'file_type'      => $file->getMimeType(),
            'file_size'      => $file->getSize(),
        ]);

        return response()->json(['file' => $record], 201);
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
