<?php

namespace App\Modules\Addons\MapaRed\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Espejo de App\Models\MapDevice, apuntando a mapared_devices (MR-06a-1, item #9990333).
 */
class MapaRedDevice extends Model
{
    use HasFactory;

    protected $table = 'mapared_devices';

    protected $fillable = [
        'name',
        'type',
        'description',
        'position_x',
        'position_y',
        'orientation',
        'layer_id',
        'parent_id',
        'data',
    ];

    protected $casts = ['data' => 'json'];

    public function layer()
    {
        return $this->belongsTo(MapaRedLayer::class, 'layer_id');
    }

    public function ports()
    {
        return $this->hasMany(MapaRedDevicePort::class, 'device_id');
    }

    public function device()
    {
        return $this->belongsTo(MapaRedDevice::class, 'parent_id');
    }

    public function devices()
    {
        return $this->hasMany(MapaRedDevice::class, 'parent_id');
    }
}
