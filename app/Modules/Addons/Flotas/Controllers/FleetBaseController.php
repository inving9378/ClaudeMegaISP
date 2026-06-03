<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Flotas\Models\FleetVehicle;

abstract class FleetBaseController extends Controller
{
    protected function clientId(): ?int
    {
        $user = auth()->user();
        return $user->hasRole(['super-administrator', 'DESARROLLADOR'])
            ? null
            : ($user->client_id ?? null);
    }

    protected function vehicleForClient(int $id): FleetVehicle
    {
        return FleetVehicle::forClient($this->clientId())->findOrFail($id);
    }
}
