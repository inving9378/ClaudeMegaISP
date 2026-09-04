<?php

namespace App\Modules\Addons\MapaRed\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Espejo de App\Models\MapLayer, apuntando a mapared_layers (MR-06a-1, item #9990333).
 */
class MapaRedLayer extends Model
{
    use HasFactory;

    protected $table = 'mapared_layers';

    protected $fillable = [
        'project_id',
        'classification',
        'type',
        'coords',
        'color',
        'weight',
        'distance',
        'route',
        'text',
        'dialog',
        'icon',
        'icon_color',
        'label',
        'data',
        'inputs',
        'level',
    ];

    protected $casts = [
        'coords' => 'json',
        'data' => 'json',
    ];

    public function service_box()
    {
        return $this->belongsTo(MapaRedLayer::class, 'service_box_id');
    }

    public function devices()
    {
        return $this->hasMany(MapaRedDevice::class, 'layer_id');
    }

    public function clients()
    {
        return $this->hasMany(MapaRedLayer::class, 'service_box_id');
    }

    public function project()
    {
        return $this->belongsTo(MapaRedProyect::class, 'project_id');
    }

    public function routes()
    {
        return $this->hasMany(MapaRedLayerRoute::class, 'layer_id');
    }

    public function fibers()
    {
        return $this->hasMany(MapaRedFiber::class, 'fiber_id');
    }

    public function layerable()
    {
        return $this->morphTo();
    }
}
