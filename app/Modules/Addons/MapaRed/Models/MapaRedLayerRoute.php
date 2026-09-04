<?php

namespace App\Modules\Addons\MapaRed\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Espejo de App\Models\MapLayerRoute, apuntando a mapared_layers_routes (MR-06a-1, item #9990333).
 */
class MapaRedLayerRoute extends Model
{
    use HasFactory;

    protected $table = 'mapared_layers_routes';

    protected $fillable = [
        'route_id',
        'layer_id',
        'position_x',
        'position_y',
        'direction',
        'input',
        'calculate_distance',
        'real_distance',
    ];

    public function route()
    {
        return $this->belongsTo(MapaRedLayer::class, 'route_id');
    }

    public function layer()
    {
        return $this->belongsTo(MapaRedLayer::class, 'layer_id');
    }
}
