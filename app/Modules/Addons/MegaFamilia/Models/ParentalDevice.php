<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParentalDevice extends BaseModel
{
    protected $table = 'parental_devices';

    protected $fillable = [
        'profile_id', 'account_id', 'name', 'model', 'os', 'os_version',
        'app_version', 'status', 'battery_level', 'last_seen_at',
        'fcm_token', 'link_token', 'linked_at',
    ];

    protected $casts = [
        'battery_level' => 'integer',
        'last_seen_at' => 'datetime',
        'linked_at' => 'datetime',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ParentalProfile::class, 'profile_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ParentalAccount::class, 'account_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(ParentalLocation::class, 'device_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(ParentalAlert::class, 'device_id');
    }
}
