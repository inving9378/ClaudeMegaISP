<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MapFiber extends Model
{
    use HasFactory;

    protected $fillable = [
        'buffer',
        'number',
        'color',
        'fiber_id',
        'parent_buffer',
        'zone'
    ];

    protected $appends = ['selected', 'model_type', 'disable', 'accept_connection'];

    public function layer()
    {
        return $this->belongsTo(MapLayer::class, 'fiber_id');
    }

    public function connections_from()
    {
        return $this->morphMany(MapDevicePortConnection::class, 'from');
    }

    public function connections_to()
    {
        return $this->morphMany(MapDevicePortConnection::class, 'to');
    }

    public function cuts()
    {
        return $this->hasMany(MapCutFiber::class, 'fiber_id');
    }

    public function getSelectedAttribute()
    {
        return false;
    }

    public function getModelTypeAttribute()
    {
        return $this::class;
    }

    public function getDisableAttribute()
    {
        return false;
    }

    public function getAcceptConnectionAttribute()
    {
        $connections = DB::select("SELECT * FROM map_devices_ports_connections WHERE from_id != to_id and from_type != to_type and ((from_id=? and from_type=?) OR (to_id=? and to_type=?))", [$this->id, $this::class, $this->id, $this::class]);
        return count($connections) < 2;
    }
}
