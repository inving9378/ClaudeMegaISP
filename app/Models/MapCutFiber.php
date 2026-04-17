<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MapCutFiber extends Model
{
    use HasFactory;

    protected $table = 'map_fibers_cut';

    protected $fillable = [
        'fiber_id',
        'layer_id',
        'state',
        'current_input',
        'route_id'
    ];

    public function layer()
    {
        return $this->belongsTo(MapLayer::class, 'layer_id');
    }

    public function fiber()
    {
        return $this->belongsTo(MapFiber::class, 'fiber_id');
    }
}
