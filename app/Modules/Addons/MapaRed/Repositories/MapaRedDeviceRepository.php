<?php

namespace App\Modules\Addons\MapaRed\Repositories;

use App\Modules\Addons\MapaRed\Models\MapaRedDevice;
use App\Repositories\BaseRepository;

/**
 * Espejo de App\Repositories\Maps\DeviceRepository, apuntando a MapaRedDevice
 * (MR-06a-2, item #9990334).
 */
class MapaRedDeviceRepository extends BaseRepository
{
    public function getModel(): MapaRedDevice
    {
        return new MapaRedDevice();
    }
}
