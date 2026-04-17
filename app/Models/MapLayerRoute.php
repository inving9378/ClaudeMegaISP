<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MapLayerRoute extends Model
{
    use HasFactory;

    protected $table = 'map_layers_routes';

    protected $fillable = ['route_id', "layer_id", 'position_x', 'position_y', 'direction', 'input', 'calculate_distance', 'real_distance'];

    public function route()
    {
        return $this->belongsTo(MapLayer::class, 'route_id');
    }

    public function layer()
    {
        return $this->belongsTo(MapLayer::class, 'layer_id');
    }
}
