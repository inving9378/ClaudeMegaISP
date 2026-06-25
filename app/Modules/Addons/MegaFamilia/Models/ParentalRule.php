<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use App\Models\BaseModel;
use App\Traits\BelongsToClientTenant;
use App\Traits\DerivesClientIspId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentalRule extends BaseModel
{
    use BelongsToClientTenant;
    use DerivesClientIspId;

    /** Tenancy MegaFamilia por cliente ISP (denormalizado), fail-closed. */
    protected string $tenantColumn = 'client_isp_id';
    protected bool $allowNullTenant = false;
    protected array $clientIspFrom = ['profile', 'profile_id'];

    protected $table = 'parental_rules';

    protected $fillable = [
        'profile_id', 'daily_limit_minutes', 'weekend_limit_minutes',
        'bedtime_start', 'bedtime_end', 'school_start', 'school_end',
        'internet_paused',
    ];

    protected $casts = [
        'daily_limit_minutes' => 'integer',
        'weekend_limit_minutes' => 'integer',
        'internet_paused' => 'boolean',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ParentalProfile::class, 'profile_id');
    }
}
