<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Modules\Addons\Flotas\Controllers\FleetBaseController;
use App\Modules\Addons\Flotas\Models\FleetAssignment;
use App\Modules\Addons\Flotas\Models\FleetClientOperator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Mis operadores" del cliente + asignación de operadores a SUS unidades (item #676, Flotas F6b).
 *
 * SEGURIDAD: solo vive en el grupo 'flotas.portal'. client_id sale SIEMPRE del guard cliente
 * (clientId()), nunca del request; user_id NUNCA se acepta (los usuarios del sistema son de Meganet).
 */
class FlotasOperadoresController extends FleetBaseController
{
    private function clienteOFalla(): int
    {
        abort_unless($this->enPortal(), 403, 'Función solo disponible en el portal.');
        $clientId = $this->clientId();
        abort_unless($clientId, 403, 'Sesión sin cliente.');

        return (int) $clientId;
    }

    private function propio(int $clientId, int $id): FleetClientOperator
    {
        return FleetClientOperator::where('client_id', $clientId)->findOrFail($id);
    }

    private function reglas(): array
    {
        return [
            'nombre'           => 'required|string|max:150',
            'telefono'         => 'nullable|string|max:30',
            'licencia_numero'  => 'nullable|string|max:50',
            'licencia_vigencia'=> 'nullable|date',
            'notas'            => 'nullable|string|max:1000',
            'activo'           => 'nullable|boolean',
        ];
    }

    public function index(): JsonResponse
    {
        $clientId = $this->clienteOFalla();

        return response()->json([
            'operadores' => FleetClientOperator::where('client_id', $clientId)->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $clientId = $this->clienteOFalla();
        $data     = $request->validate($this->reglas());
        $data['client_id'] = $clientId;

        return response()->json(['operador' => FleetClientOperator::create($data)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $clientId = $this->clienteOFalla();
        $op       = $this->propio($clientId, $id);
        $op->update($request->validate($this->reglas()));

        return response()->json(['operador' => $op->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $clientId = $this->clienteOFalla();
        $this->propio($clientId, $id)->delete();

        return response()->json(['ok' => true]);
    }

    // ── Asignaciones ─────────────────────────────────────────────────────────

    public function asignaciones(int $vehicleId): JsonResponse
    {
        $this->clienteOFalla();
        $this->vehicleForClient($vehicleId);

        $rows = FleetAssignment::where('vehicle_id', $vehicleId)
            ->orderByDesc('since')->orderByDesc('id')->get()
            ->map(fn($a) => [
                'id'                 => $a->id,
                'client_operator_id' => $a->client_operator_id,
                'operator_name'      => $a->display_name,
                'since'              => $a->since,
                'until'              => $a->until,
                'is_active'          => $a->is_active,
                'notes'              => $a->notes,
            ]);

        return response()->json(['assignments' => $rows]);
    }

    public function asignar(Request $request, int $vehicleId): JsonResponse
    {
        $clientId = $this->clienteOFalla();
        $this->vehicleForClient($vehicleId);

        $data = $request->validate([
            'client_operator_id' => 'nullable|integer',
            'operator_name'      => 'nullable|string|max:150',
            'since'              => 'required|date',
            'until'              => 'nullable|date|after_or_equal:since',
            'notes'              => 'nullable|string|max:1000',
        ]);

        // Exactamente uno de los dos.
        $tieneId     = ! empty($data['client_operator_id']);
        $tieneNombre = isset($data['operator_name']) && trim($data['operator_name']) !== '';
        if ($tieneId === $tieneNombre) {
            throw ValidationException::withMessages([
                'client_operator_id' => 'Indica un operador de tu lista o un nombre, no ambos ni ninguno.',
            ]);
        }

        if ($tieneId) {
            $op = $this->propio($clientId, (int) $data['client_operator_id']);
            $data['operator_name']      = $op->nombre;
            $data['client_operator_id'] = $op->id;
        } else {
            $data['operator_name']      = trim($data['operator_name']);
            $data['client_operator_id'] = null;
        }

        $assignment = DB::transaction(function () use ($vehicleId, $data) {
            FleetAssignment::where('vehicle_id', $vehicleId)
                ->whereNull('until')
                ->update(['until' => $data['since']]);

            // user_id NUNCA: se fija explícitamente nulo.
            return FleetAssignment::create(array_merge($data, [
                'vehicle_id' => $vehicleId,
                'user_id'    => null,
            ]));
        });

        return response()->json(['assignment' => [
            'id'                 => $assignment->id,
            'client_operator_id' => $assignment->client_operator_id,
            'operator_name'      => $assignment->display_name,
            'since'              => $assignment->since,
            'until'              => $assignment->until,
            'is_active'          => $assignment->is_active,
        ]], 201);
    }
}
