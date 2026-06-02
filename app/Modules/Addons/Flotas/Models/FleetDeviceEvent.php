<?php

namespace App\Modules\Addons\Flotas\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FleetDeviceEvent extends Model
{
    use SoftDeletes;

    protected $table = 'fleet_device_events';

    const UPDATED_AT = null; // solo created_at

    protected $fillable = [
        'vehicle_id', 'device_id', 'event_type', 'metadata', 'occurred_at',
    ];

    protected $casts = [
        'metadata'    => 'array',
        'occurred_at' => 'datetime',
        'created_at'  => 'datetime',
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
}
