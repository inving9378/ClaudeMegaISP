<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use App\Models\BaseModel;
use App\Traits\BelongsToClientTenant;
use App\Traits\DerivesClientIspId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentalAppBlock extends BaseModel
{
    use BelongsToClientTenant;
    use DerivesClientIspId;

    /** Tenancy MegaFamilia por cliente ISP (denormalizado), fail-closed. */
    protected string $tenantColumn = 'client_isp_id';
    protected bool $allowNullTenant = false;
    protected array $clientIspFrom = ['profile', 'profile_id'];

    protected $table = 'parental_app_blocks';

    protected $fillable = [
        'profile_id', 'package_name', 'app_name', 'category',
        'blocked', 'schedule_start', 'schedule_end',
    ];

    protected $casts = ['blocked' => 'boolean'];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ParentalProfile::class, 'profile_id');
    }
}
