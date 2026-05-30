<?php

namespace App\Modules\Addons\WarRoom\Models;

use Illuminate\Database\Eloquent\Model;

class InsightsCache extends Model
{
    protected $table = 'warroom_insights_cache';

    protected $guarded = [];

    protected $casts = [
        'insights'     => 'array',
        'generated_at' => 'datetime',
    ];

    public function isFresh(int $maxAgeMinutes = 60): bool
    {
        return $this->generated_at?->gt(now()->subMinutes($maxAgeMinutes)) ?? false;
    }
}
