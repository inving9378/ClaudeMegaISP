<?php

namespace App\Modules\Addons\MapaRed\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Espejo de App\Models\MapFiber, apuntando a mapared_fibers (MR-06a-1, item #9990333).
 */
class MapaRedFiber extends Model
{
    use HasFactory;

    protected $table = 'mapared_fibers';

    protected $fillable = [
        'buffer',
        'number',
        'color',
        'fiber_id',
        'parent_buffer',
        'zone',
    ];

    public function layer()
    {
        return $this->belongsTo(MapaRedLayer::class, 'fiber_id');
    }

    public function connections_from()
    {
        return $this->morphMany(MapaRedDevicePortConnection::class, 'from');
    }

    public function connections_to()
    {
        return $this->morphMany(MapaRedDevicePortConnection::class, 'to');
    }

    public function cuts()
    {
        return $this->hasMany(MapaRedCutFiber::class, 'fiber_id');
    }
}
