<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Modules\Addons\Flotas\Models\FleetAssignment;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Services\EmployeeDocumentPackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FleetAssignmentController extends FleetBaseController
{
    public function index(int $vehicleId): JsonResponse
    {
        $this->authorize('fleet.view');

        $this->vehicleForClient($vehicleId);

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

    public function store(Request $request, int $vehicleId): JsonResponse
    {
        $this->authorize('fleet.assign');

        $this->vehicleForClient($vehicleId);

        $data = $request->validate([
            'user_id'    => 'required|integer',
            'department' => 'nullable|string|max:150',
            'since'      => 'required|date',
            'until'      => 'nullable|date|after_or_equal:since',
            'notes'      => 'nullable|string',
        ]);

        $assignment = DB::transaction(function () use ($vehicleId, $data) {
            FleetAssignment::where('vehicle_id', $vehicleId)
                ->whereNull('until')
                ->update(['until' => $data['since']]);

            return FleetAssignment::create(array_merge($data, [
                'vehicle_id' => $vehicleId,
                'created_by' => auth()->id(),
            ]));
        });

        $this->regenerarDocumentosColaborador($data['user_id']);

        return response()->json([
            'assignment' => array_merge($assignment->load('operator')->toArray(), [
                'is_active'     => $assignment->is_active,
                'operator_name' => $assignment->operator?->name,
            ]),
        ], 201);
    }

    /**
     * Item roadmap #872 (Hijo D3). Al asignar vehiculo a un colaborador, su responsiva vehicular
     * (si el catalogo de su puesto la incluye) debe reflejar placas/marca/modelo sin recapturar
     * nada. Best-effort: un fallo aqui jamas debe tumbar la asignacion del vehiculo.
     */
    private function regenerarDocumentosColaborador(int $userId): void
    {
        try {
            $colaborador = TalentoColaborador::where('user_id', $userId)->first();
            if ($colaborador) {
                app(EmployeeDocumentPackageService::class)->generateForColaborador($colaborador);
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo regenerar documentos del colaborador tras asignar vehiculo: ' . $e->getMessage());
        }
    }
}
