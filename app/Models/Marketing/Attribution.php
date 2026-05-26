<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attribution extends Model
{
    protected $table = 'marketing_attributions';

    protected $fillable = [
        'lead_id', 'campaign_id', 'channel_id', 'touchpoint', 'weight', 'occurred_at', 'metadata',
    ];

    protected $casts = [
        'weight' => 'decimal:3',
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
