<?php

namespace App\Repositories;

use App\Models\MapSource;
use App\Repositories\BaseRepository;

class MapSourceRepository extends BaseRepository
{
    public function getModel(): MapSource
    {
        return new MapSource();
    }
}
