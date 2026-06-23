<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Services\Tenant\CurrentClientResolver;

abstract class FleetBaseController extends Controller
{
    /**
     * Cliente actual. Delega en el resolver unificado del proyecto, que para el
     * guard web admin replica EXACTAMENTE la lógica previa (roles internos → null;
     * el resto → su client_id). Sin cambio de comportamiento para Flotas.
     */
    protected function clientId(): ?int
    {
        return app(CurrentClientResolver::class)->resolve();
    }

    protected function vehicleForClient(int $id): FleetVehicle
    {
        return FleetVehicle::forClient($this->clientId())->findOrFail($id);
    }
}
