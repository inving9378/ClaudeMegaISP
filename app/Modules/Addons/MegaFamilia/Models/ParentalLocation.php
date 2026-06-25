<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use App\Models\BaseModel;
use App\Traits\BelongsToClientTenant;
use App\Traits\DerivesClientIspId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentalLocation extends BaseModel
{
    use BelongsToClientTenant;
    use DerivesClientIspId;

    /** Tenancy MegaFamilia por cliente ISP (denormalizado), fail-closed. */
    protected string $tenantColumn = 'client_isp_id';
    protected bool $allowNullTenant = false;
    protected array $clientIspFrom = ['device', 'device_id'];

    protected $table = 'parental_locations';
    public $timestamps = false; // only `recorded_at`

    protected $fillable = ['device_id', 'lat', 'lng', 'accuracy', 'battery', 'recorded_at'];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'accuracy' => 'integer',
        'battery' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(ParentalDevice::class, 'device_id');
    }
}
