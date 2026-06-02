<?php

namespace App\Modules\Addons\Flotas\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class FleetDevice extends BaseModel
{
    use SoftDeletes;

    protected $table = 'fleet_devices';

    protected $fillable = [
        'vehicle_id', 'imei', 'brand', 'model', 'sim_number', 'sim_carrier',
        'status', 'last_seen_at', 'total_pings', 'installed_at', 'notes',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'installed_at' => 'datetime',
        'total_pings'  => 'integer',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function vehicle()
    {
        return $this->belongsTo(FleetVehicle::class, 'vehicle_id');
    }

    public function positions()
    {
        return $this->hasMany(FleetPosition::class, 'device_id');
    }

    public function events()
    {
        return $this->hasMany(FleetDeviceEvent::class, 'device_id');
    }

    public function lastPosition()
    {
        return $this->hasOne(FleetPosition::class, 'device_id')->latestOfMany('recorded_at');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    // Multi-tenancy: filtra por el client_id del vehículo dueño.
    public function scopeForClient(Builder $q, ?int $clientId): Builder
    {
        return $q->whereHas('vehicle', fn($v) => $v->forClient($clientId));
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }
}
