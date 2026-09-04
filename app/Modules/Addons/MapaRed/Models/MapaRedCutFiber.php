<?php

namespace App\Modules\Addons\MapaRed\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Espejo de App\Models\MapCutFiber, apuntando a mapared_fibers_cut (MR-06a-1, item #9990333).
 */
class MapaRedCutFiber extends Model
{
    use HasFactory;

    protected $table = 'mapared_fibers_cut';

    protected $fillable = [
        'fiber_id',
        'layer_id',
        'state',
        'current_input',
        'route_id',
    ];

    public function layer()
    {
        return $this->belongsTo(MapaRedLayer::class, 'layer_id');
    }

    public function fiber()
    {
        return $this->belongsTo(MapaRedFiber::class, 'fiber_id');
    }
}
