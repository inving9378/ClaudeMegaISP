<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyPingStatistic extends BaseModel
{
    protected $fillable = [
        'client_id',
        'date',
        'avg_ms',
        'min_ms',
        'max_ms',
        'uptime_percent',
        'total_checks',
        'down_checks',
    ];

    protected $casts = [
        'date'           => 'date',
        'avg_ms'         => 'float',
        'min_ms'         => 'float',
        'max_ms'         => 'float',
        'uptime_percent' => 'float',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function scopeForClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }
}
