<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ParentalAccount extends BaseModel
{
    protected $table = 'parental_accounts';

    protected $fillable = [
        'client_id', 'plan_id', 'status', 'licensed_at', 'expires_at',
        'terms_accepted_at', 'terms_ip',
    ];

    protected $casts = [
        'licensed_at' => 'datetime',
        'expires_at' => 'datetime',
        'terms_accepted_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ParentalPlan::class, 'plan_id');
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(ParentalProfile::class, 'account_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(ParentalDevice::class, 'account_id');
    }

    public function license(): HasOne
    {
        return $this->hasOne(ParentalLicense::class, 'account_id')->latestOfMany();
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(ParentalAlert::class, 'account_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ParentalEvent::class, 'account_id');
    }
}
