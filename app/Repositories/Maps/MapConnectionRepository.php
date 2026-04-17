<?php

namespace App\Repositories\Maps;

use App\Models\MapDevicePortConnection;
use App\Repositories\BaseRepository;

class MapConnectionRepository extends BaseRepository
{
    public function getModel(): MapDevicePortConnection
    {
        return new MapDevicePortConnection();
    }
}
