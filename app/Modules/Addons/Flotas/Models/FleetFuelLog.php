<?php

namespace App\Modules\Addons\Flotas\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

class FleetFuelLog extends BaseModel
{
    protected $table = 'fleet_fuel_log';

    protected $fillable = [
        'vehicle_id', 'refuel_date', 'liters', 'cost', 'km_at_refuel', 'octane', 'station_name',
    ];

    protected $casts = [
        'refuel_date' => 'date',
        'liters'      => 'decimal:2',
        'cost'        => 'decimal:2',
        'km_at_refuel'=> 'integer',
    ];

    public function vehicle()
    {
        return $this->belongsTo(FleetVehicle::class, 'vehicle_id');
    }

    public function getCostPerLiterAttribute(): ?float
    {
        return $this->liters > 0 ? round($this->cost / $this->liters, 2) : null;
    }

    public function scopeForClient(Builder $q, ?int $clientId): Builder
    {
        return $q->whereHas('vehicle', fn($v) => $clientId
            ? $v->where('client_id', $clientId)
            : $v->whereNull('client_id'));
    }
}
