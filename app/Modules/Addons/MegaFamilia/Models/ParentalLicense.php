<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentalLicense extends BaseModel
{
    protected $table = 'parental_licenses';

    protected $fillable = [
        'account_id', 'plan_id', 'status', 'activated_at', 'expires_at',
        'suspended_at', 'suspended_reason',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(ParentalAccount::class, 'account_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ParentalPlan::class, 'plan_id');
    }
}
