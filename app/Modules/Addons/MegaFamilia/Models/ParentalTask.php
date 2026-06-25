<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use App\Models\BaseModel;
use App\Traits\BelongsToClientTenant;
use App\Traits\DerivesClientIspId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentalTask extends BaseModel
{
    use BelongsToClientTenant;
    use DerivesClientIspId;

    /** Tenancy MegaFamilia por cliente ISP (denormalizado), fail-closed. */
    protected string $tenantColumn = 'client_isp_id';
    protected bool $allowNullTenant = false;
    protected array $clientIspFrom = ['profile', 'profile_id'];

    protected $table = 'parental_tasks';

    protected $fillable = [
        'profile_id', 'title', 'description', 'reward_type',
        'reward_value', 'reward_detail', 'points', 'status',
        'completed_at', 'approved_at', 'photo_proof',
        'due_date', 'priority', 'notes',
    ];

    protected $casts = [
        'reward_value' => 'integer',
        'points' => 'integer',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
        'due_date' => 'date',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ParentalProfile::class, 'profile_id');
    }
}
