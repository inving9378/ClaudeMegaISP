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

    /**
     * Mismos campos que App\Models\MapFiber que usa la pantalla de conexiones. `disable` y
     * `accept_connection` no se copian: el frontend no los lee y el segundo hace una consulta
     * por cada hilo.
     */
    protected $appends = ['selected', 'model_type'];

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

    public function getSelectedAttribute()
    {
        return false;
    }

    public function getModelTypeAttribute()
    {
        return $this::class;
    }
}
