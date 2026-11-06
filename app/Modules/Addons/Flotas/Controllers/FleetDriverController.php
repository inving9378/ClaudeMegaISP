<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * #920 — Ficha del conductor: identidad básica del operador (users interno) que se muestra
 * como encabezado antes de sus documentos. Mismo filtro que FleetVehicleController::operators
 * (excluye rol client, exige tener algún rol) para no exponer aquí cuentas de clientes/espejo.
 */
class FleetDriverController extends FleetBaseController
{
    public function show(int $id): JsonResponse
    {
        $this->autorizar('fleet.view');

        // Alcance: el conductor es un usuario interno sin client_id (ver FleetDocument::scopeForClient),
        // así que solo existe bajo el scope interno. Con scope de cliente/portal se responde 404.
        abort_if($this->clientId() !== null, 404);

        $driver = User::query()
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'client'))
            ->has('roles')
            ->findOrFail($id);

        return response()->json([
            'driver' => [
                'id'    => $driver->id,
                'name'  => trim("{$driver->name} {$driver->father_last_name} {$driver->mother_last_name}"),
                'email' => $driver->email,
            ],
        ]);
    }
}
