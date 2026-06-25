<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use App\Models\BaseModel;
use App\Models\User;
use App\Traits\BelongsToClientTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ParentalAccount extends BaseModel
{
    use BelongsToClientTenant;

    /** Tenancy MegaFamilia: client_isp_id es la FUENTE (raíz). Fail-closed. */
    protected string $tenantColumn = 'client_isp_id';
    protected bool $allowNullTenant = false;

    protected $table = 'parental_accounts';

    protected $fillable = [
        'user_id', 'client_isp_id', 'plan_id', 'status', 'licensed_at', 'expires_at',
        'terms_accepted_at', 'terms_ip',
    ];

    protected $casts = [
        'licensed_at' => 'datetime',
        'expires_at' => 'datetime',
        'terms_accepted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function clientIsp(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Client::class, 'client_isp_id');
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
