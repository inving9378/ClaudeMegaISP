<?php

namespace App\Modules\Addons\MegaFamilia\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentalTask extends BaseModel
{
    protected $table = 'parental_tasks';

    protected $fillable = [
        'profile_id', 'title', 'description', 'reward_type',
        'reward_value', 'reward_detail', 'points', 'status',
        'completed_at', 'approved_at', 'photo_proof',
    ];

    protected $casts = [
        'reward_value' => 'integer',
        'points' => 'integer',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ParentalProfile::class, 'profile_id');
    }
}
