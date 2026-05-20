<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentalGeofence extends BaseModel
{
    protected $table = 'parental_geofences';

    protected $fillable = [
        'profile_id', 'name', 'type', 'lat', 'lng', 'radius_meters',
        'alert_on_enter', 'alert_on_exit', 'active',
    ];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'radius_meters' => 'integer',
        'alert_on_enter' => 'boolean',
        'alert_on_exit' => 'boolean',
        'active' => 'boolean',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ParentalProfile::class, 'profile_id');
    }
}
