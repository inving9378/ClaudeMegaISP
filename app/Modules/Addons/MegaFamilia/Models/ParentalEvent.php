<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentalEvent extends BaseModel
{
    protected $table = 'parental_events';
    public $timestamps = false; // only `created_at`

    protected $fillable = [
        'account_id', 'profile_id', 'device_id', 'action', 'detail', 'ip', 'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(ParentalAccount::class, 'account_id');
    }
}
