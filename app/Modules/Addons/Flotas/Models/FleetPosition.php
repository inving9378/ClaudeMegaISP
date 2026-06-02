<?php

namespace App\Modules\Addons\Flotas\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// NO extiende BaseModel a propósito: tabla de alto volumen (miles de pings),
// no queremos LogsActivity por cada inserción.
class FleetPosition extends Model
{
    use SoftDeletes;

    protected $table = 'fleet_positions';

    // Manejamos recorded_at / received_at manualmente; sin created_at/updated_at.
    public $timestamps = false;

    protected $fillable = [
        'vehicle_id', 'device_id', 'lat', 'lng', 'speed', 'heading',
        'altitude', 'satellites', 'hdop', 'ignition', 'battery',
        'recorded_at', 'received_at',
    ];

    protected $casts = [
        'lat'         => 'float',
        'lng'         => 'float',
        'speed'       => 'float',
        'heading'     => 'float',
        'altitude'    => 'float',
        'satellites'  => 'integer',
        'hdop'        => 'float',
        'ignition'    => 'boolean',
        'battery'     => 'float',
        'recorded_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(FleetVehicle::class, 'vehicle_id');
    }

    public function device()
    {
        return $this->belongsTo(FleetDevice::class, 'device_id');
    }

    public function scopeForClient(Builder $q, ?int $clientId): Builder
    {
        return $q->whereHas('vehicle', fn($v) => $v->forClient($clientId));
    }

    public function scopeBetween(Builder $q, $from, $to): Builder
    {
        return $q->whereBetween('recorded_at', [$from, $to]);
    }
}
