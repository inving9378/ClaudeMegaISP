<?php

namespace App\Repositories\Maps;

use App\Models\MapDevice;
use App\Repositories\BaseRepository;

class DeviceRepository extends BaseRepository
{
    public function getModel(): MapDevice
    {
        return new MapDevice();
    }
}
