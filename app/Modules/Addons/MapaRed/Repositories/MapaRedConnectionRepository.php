<?php

namespace App\Modules\Addons\MapaRed\Repositories;

use App\Modules\Addons\MapaRed\Models\MapaRedDevicePortConnection;
use App\Repositories\BaseRepository;

/**
 * Espejo de App\Repositories\Maps\MapConnectionRepository, apuntando a
 * MapaRedDevicePortConnection (MR-06a-2, item #9990334).
 */
class MapaRedConnectionRepository extends BaseRepository
{
    public function getModel(): MapaRedDevicePortConnection
    {
        return new MapaRedDevicePortConnection();
    }
}
