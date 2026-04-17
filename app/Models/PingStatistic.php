<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PingStatistic extends BaseModel
{
    protected $fillable = [
        'client_id',
        'ip_address',
        'avg_ms',
        'min_ms',
        'max_ms',
        'jitter_ms',
        'packet_loss',
        'status',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'avg_ms'      => 'float',
        'min_ms'      => 'float',
        'max_ms'      => 'float',
        'jitter_ms'   => 'float',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function scopeForClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'up'      => 'positive',
            'down'    => 'negative',
            'timeout' => 'warning',
            default   => 'grey',
        };
    }
}
