<?php

namespace App\Modules\Addons\Flotas\Models;

use App\Models\BaseModel;
use App\Models\User;

class FleetAssignment extends BaseModel
{
    protected $table = 'fleet_assignments';

    protected $fillable = [
        'vehicle_id', 'user_id', 'department', 'since', 'until', 'notes',
    ];

    protected $casts = [
        'since' => 'date',
        'until' => 'date',
    ];

    public function vehicle()
    {
        return $this->belongsTo(FleetVehicle::class, 'vehicle_id');
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getIsActiveAttribute(): bool
    {
        return is_null($this->until) || $this->until->isFuture();
    }
}
