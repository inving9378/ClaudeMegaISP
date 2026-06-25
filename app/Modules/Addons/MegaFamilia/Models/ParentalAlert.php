<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use App\Models\BaseModel;
use App\Traits\BelongsToClientTenant;
use App\Traits\DerivesClientIspId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentalAlert extends BaseModel
{
    use BelongsToClientTenant;
    use DerivesClientIspId;

    /** Tenancy MegaFamilia por cliente ISP (denormalizado), fail-closed. */
    protected string $tenantColumn = 'client_isp_id';
    protected bool $allowNullTenant = false;
    protected array $clientIspFrom = ['account', 'account_id'];

    protected $table = 'parental_alerts';

    protected $fillable = [
        'account_id', 'profile_id', 'device_id', 'type', 'detail', 'read_at',
    ];

    protected $casts = ['read_at' => 'datetime'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(ParentalAccount::class, 'account_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ParentalProfile::class, 'profile_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(ParentalDevice::class, 'device_id');
    }
}
