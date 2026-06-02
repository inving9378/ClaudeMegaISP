<?php

namespace App\Modules\Addons\Flotas\Models;

use App\Models\BaseModel;

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
}
