<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ParentalProfile extends BaseModel
{
    protected $table = 'parental_profiles';

    protected $fillable = [
        'account_id', 'name', 'age', 'school_level', 'profile_type',
        'photo', 'active',
    ];

    protected $casts = [
        'age' => 'integer',
        'active' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(ParentalAccount::class, 'account_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(ParentalDevice::class, 'profile_id');
    }

    public function rules(): HasOne
    {
        return $this->hasOne(ParentalRule::class, 'profile_id');
    }

    public function appBlocks(): HasMany
    {
        return $this->hasMany(ParentalAppBlock::class, 'profile_id');
    }

    public function webBlocks(): HasMany
    {
        return $this->hasMany(ParentalWebBlock::class, 'profile_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ParentalSchedule::class, 'profile_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ParentalTask::class, 'profile_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ParentalRequest::class, 'profile_id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(ParentalReward::class, 'profile_id');
    }

    public function geofences(): HasMany
    {
        return $this->hasMany(ParentalGeofence::class, 'profile_id');
    }
}
