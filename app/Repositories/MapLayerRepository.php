<?php

namespace App\Repositories;

use App\Models\MapLayer;
use App\Repositories\BaseRepository;

class MapLayerRepository extends BaseRepository
{
    public function getModel(): MapLayer
    {
        return new MapLayer();
    }
}
