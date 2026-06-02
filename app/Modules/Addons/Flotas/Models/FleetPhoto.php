<?php

namespace App\Modules\Addons\Flotas\Models;

use App\Models\BaseModel;

class FleetPhoto extends BaseModel
{
    protected $table = 'fleet_photos';

    protected $fillable = [
        'vehicle_id', 'photo_type', 'file_path', 'taken_at',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(FleetVehicle::class, 'vehicle_id');
    }
}
