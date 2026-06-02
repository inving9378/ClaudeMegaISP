<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Flotas\Models\FleetAssignment;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FleetAssignmentController extends Controller
{
    // GET /flotas/api/vehiculos/{id}/asignaciones
    public function index(int $vehicleId): JsonResponse
    {
        $this->authorize('fleet.view');

        // Verifica pertenencia del vehículo al cliente del usuario
        FleetVehicle::where(fn($q) => $this->scopeClient($q))->findOrFail($vehicleId);

        $assignments = FleetAssignment::with('operator')
            ->where('vehicle_id', $vehicleId)
            ->orderByDesc('since')
            ->orderByDesc('id')
            ->get()
            ->map(fn($a) => array_merge($a->toArray(), [
                'is_active'     => $a->is_active,
                'operator_name' => $a->operator?->name,
            ]));

        return response()->json(['assignments' => $assignments]);
    }

    // POST /flotas/api/vehiculos/{id}/asignaciones
    public function store(Request $request, int $vehicleId): JsonResponse
    {
        $this->authorize('fleet.assign');

        FleetVehicle::where(fn($q) => $this->scopeClient($q))->findOrFail($vehicleId);

        $data = $request->validate([
            'user_id'    => 'required|integer',
            'department' => 'nullable|string|max:150',
            'since'      => 'required|date',
            'until'      => 'nullable|date|after_or_equal:since',
            'notes'      => 'nullable|string',
        ]);

        $assignment = DB::transaction(function () use ($vehicleId, $data) {
            // Cierra la asignación activa anterior (until = NULL) el día previo a la nueva.
            FleetAssignment::where('vehicle_id', $vehicleId)
                ->whereNull('until')
                ->update(['until' => $data['since']]);

            return FleetAssignment::create(array_merge($data, [
                'vehicle_id' => $vehicleId,
                'created_by' => auth()->id(),
            ]));
        });

        return response()->json([
            'assignment' => array_merge($assignment->load('operator')->toArray(), [
                'is_active'     => $assignment->is_active,
                'operator_name' => $assignment->operator?->name,
            ]),
        ], 201);
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
