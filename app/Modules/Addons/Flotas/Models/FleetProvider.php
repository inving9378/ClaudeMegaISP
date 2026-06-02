<?php

namespace App\Modules\Addons\Flotas\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class FleetProvider extends BaseModel
{
    use SoftDeletes;

    protected $table = 'fleet_providers';

    protected $fillable = [
        'client_id', 'name', 'type', 'contact_name', 'phone', 'email', 'address', 'notes',
    ];

    public function maintenances()
    {
        return $this->hasMany(FleetMaintenance::class, 'provider_id');
    }
}
