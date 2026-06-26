<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use App\Models\BaseModel;
use App\Traits\BelongsToClientTenant;
use App\Traits\DerivesClientIspId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentalGeofence extends BaseModel
{
    use BelongsToClientTenant;
    use DerivesClientIspId;

    /** Tenancy MegaFamilia por cliente ISP (denormalizado), fail-closed. */
    protected string $tenantColumn = 'client_isp_id';
    protected bool $allowNullTenant = false;
    protected array $clientIspFrom = ['profile', 'profile_id'];

    protected $table = 'parental_geofences';

    protected $fillable = [
        'profile_id', 'name', 'address', 'type', 'lat', 'lng', 'radius_meters',
        'coordinates', 'alert_on_enter', 'alert_on_exit', 'active',
    ];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'radius_meters' => 'integer',
        'coordinates' => 'array',
        'alert_on_enter' => 'boolean',
        'alert_on_exit' => 'boolean',
        'active' => 'boolean',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ParentalProfile::class, 'profile_id');
    }
}
