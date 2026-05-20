<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentalSchedule extends BaseModel
{
    protected $table = 'parental_schedules';

    protected $fillable = [
        'profile_id', 'name', 'days', 'start_time', 'end_time',
        'action', 'active',
    ];

    protected $casts = [
        'days' => 'array',
        'active' => 'boolean',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ParentalProfile::class, 'profile_id');
    }
}
