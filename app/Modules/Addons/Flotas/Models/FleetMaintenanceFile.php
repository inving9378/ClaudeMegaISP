<?php

namespace App\Modules\Addons\Flotas\Models;

use App\Models\BaseModel;

class FleetMaintenanceFile extends BaseModel
{
    protected $table = 'fleet_maintenance_files';

    protected $fillable = [
        'maintenance_id', 'file_path', 'file_name', 'file_type', 'file_size', 'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'file_size'   => 'integer',
    ];

    public function maintenance()
    {
        return $this->belongsTo(FleetMaintenance::class, 'maintenance_id');
    }
}
