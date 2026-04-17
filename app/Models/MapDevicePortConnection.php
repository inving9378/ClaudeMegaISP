<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

class MapDevicePortConnection extends Model
{
    use HasFactory;

    protected $table = 'map_devices_ports_connections';

    protected $fillable = [
        'from_id',
        'to_id',
        'from_type',
        'layer_id',
        'to_type',
        'type',
        'color',
        'width',
        'animate',
        'data',
        'connection_type',
        'from_element',
        'to_element',
        'from_route_id',
        'to_route_id',
        'from_input',
        'to_input',
    ];

    protected $with = ['from', 'to'];

    protected $casts = ['data' => 'json'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($obj) {
            if ($obj->from_type === MapDevicePort::class && $obj->to_type === MapDevicePort::class) {
                $obj->connection_type = 'port-to-port';
            } else if ($obj->from_type === MapFiber::class && $obj->to_type === MapFiber::class) {
                $obj->connection_type = 'fiber-to-fiber';
            } else {
                $obj->connection_type = 'fiber-to-port';
            }
        });
    }

    public function from(): MorphTo
    {
        return $this->morphTo();
    }

    public function to(): MorphTo
    {
        return $this->morphTo();
    }

    public function layer()
    {
        return $this->belongsTo(MapLayer::class, 'layer_id');
    }

    public function removeOrphansConnections()
    {
        $records = DB::select('with objects AS (SELECT id, from_type model_type, from_id model_id FROM map_devices_ports_connections UNION SELECT id, to_type model_type, to_id model_id FROM map_devices_ports_connections) SELECT DISTINCT * FROM objects');
        $ids = [];
        foreach ($records as $r) {
            $exists = DB::table((new $r->model_type)->getTable())->where('id', $r->model_id)->exists();
            if (!$exists) {
                $ids[] = $r->id;
            }
        }
        if (count($ids) > 0) {
            DB::table('map_devices_ports_connections')->whereIn('id', $ids)->delete();
        }
    }
}
