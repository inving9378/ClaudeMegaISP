<?php

namespace App\Modules\Addons\Flotas\Models;

use App\Models\BaseModel;
use App\Traits\BelongsToClientTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class FleetProvider extends BaseModel
{
    use SoftDeletes;
    use BelongsToClientTenant;

    /** Módulo interno Meganet: client_id NULL = flota propia (visible para admin). */
    protected bool $allowNullTenant = true;

    protected $table = 'fleet_providers';

    protected $fillable = [
        'client_id', 'name', 'type', 'contact_name', 'phone', 'email', 'address', 'notes',
    ];

    public function maintenances()
    {
        return $this->hasMany(FleetMaintenance::class, 'provider_id');
    }

    // scopeForClient() lo provee el trait BelongsToClientTenant (allowNullTenant=true).
}
