<?php

namespace App\Modules\Addons\Flotas\Models;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class FleetNotificationPreference extends BaseModel
{
    use SoftDeletes;

    protected $table = 'fleet_notification_preferences';

    protected $fillable = [
        'user_id', 'vehicle_id', 'geofence_id', 'event_types', 'channels', 'active',
    ];

    protected $casts = [
        'event_types' => 'array',
        'channels'    => 'array',
        'active'      => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(FleetVehicle::class, 'vehicle_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('active', true);
    }
}
