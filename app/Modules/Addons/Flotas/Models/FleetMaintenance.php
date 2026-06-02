<?php

namespace App\Modules\Addons\Flotas\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class FleetMaintenance extends BaseModel
{
    use SoftDeletes;

    protected $table = 'fleet_maintenances';

    protected $fillable = [
        'vehicle_id', 'type', 'service_date', 'service_km', 'works', 'description',
        'provider_id', 'mechanic_name', 'labor_cost', 'parts_cost', 'other_cost',
        'next_service_date', 'next_service_km', 'is_draft',
    ];

    protected $casts = [
        'service_date'      => 'date',
        'next_service_date' => 'date',
        'works'             => 'array',
        'labor_cost'        => 'decimal:2',
        'parts_cost'        => 'decimal:2',
        'other_cost'        => 'decimal:2',
        'total_cost'        => 'decimal:2',
        'is_draft'          => 'boolean',
    ];

    public function vehicle()
    {
        return $this->belongsTo(FleetVehicle::class, 'vehicle_id');
    }

    public function provider()
    {
        return $this->belongsTo(FleetProvider::class, 'provider_id');
    }

    public function files()
    {
        return $this->hasMany(FleetMaintenanceFile::class, 'maintenance_id');
    }
}
