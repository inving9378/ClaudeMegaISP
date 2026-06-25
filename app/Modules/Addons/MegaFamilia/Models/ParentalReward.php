<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use App\Models\BaseModel;
use App\Traits\BelongsToClientTenant;
use App\Traits\DerivesClientIspId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentalReward extends BaseModel
{
    use BelongsToClientTenant;
    use DerivesClientIspId;

    /** Tenancy MegaFamilia por cliente ISP (denormalizado), fail-closed. */
    protected string $tenantColumn = 'client_isp_id';
    protected bool $allowNullTenant = false;
    protected array $clientIspFrom = ['profile', 'profile_id'];

    protected $table = 'parental_rewards';

    protected $fillable = [
        'profile_id', 'type', 'value', 'detail', 'source_task_id',
        'granted_at', 'expires_at', 'used_at',
    ];

    protected $casts = [
        'value' => 'integer',
        'granted_at' => 'datetime',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ParentalProfile::class, 'profile_id');
    }

    public function sourceTask(): BelongsTo
    {
        return $this->belongsTo(ParentalTask::class, 'source_task_id');
    }
}
