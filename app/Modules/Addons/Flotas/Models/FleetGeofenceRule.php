<?php

namespace App\Modules\Addons\Flotas\Models;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class FleetGeofenceRule extends BaseModel
{
    use SoftDeletes;

    protected $table = 'fleet_geofence_rules';

    protected $fillable = [
        'name', 'description', 'user_id', 'client_id',
        'vehicle_ids', 'geofence_ids', 'event_types',
        'time_from', 'time_to', 'days_of_week', 'channels', 'active',
    ];

    protected $casts = [
        'vehicle_ids'  => 'array',
        'geofence_ids' => 'array',
        'event_types'  => 'array',
        'days_of_week' => 'array',
        'channels'     => 'array',
        'active'       => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('active', true);
    }
}
