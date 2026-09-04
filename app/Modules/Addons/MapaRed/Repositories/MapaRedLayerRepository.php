<?php

namespace App\Modules\Addons\MapaRed\Repositories;

use App\Modules\Addons\MapaRed\Models\MapaRedLayer;
use App\Repositories\BaseRepository;

/**
 * Espejo de App\Repositories\MapLayerRepository, apuntando a MapaRedLayer (MR-06a-4, item #9990336).
 */
class MapaRedLayerRepository extends BaseRepository
{
    public function getModel(): MapaRedLayer
    {
        return new MapaRedLayer();
    }
}
