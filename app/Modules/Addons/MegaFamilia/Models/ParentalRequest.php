<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentalRequest extends BaseModel
{
    protected $table = 'parental_requests';

    protected $fillable = [
        'profile_id', 'device_id', 'type', 'detail', 'message',
        'status', 'responded_at', 'expires_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ParentalProfile::class, 'profile_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(ParentalDevice::class, 'device_id');
    }
}
