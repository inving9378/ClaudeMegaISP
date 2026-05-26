<?php

namespace App\Modules\Addons\Marketing\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignSchedule extends BaseModel
{
    protected $table = 'campaign_schedules';

    protected $fillable = [
        'campaign_id', 'campaign_content_id', 'channel',
        'scheduled_at', 'published_at', 'status', 'response_data', 'retry_count',
    ];

    protected $casts = [
        'scheduled_at'  => 'datetime',
        'published_at'  => 'datetime',
        'response_data' => 'array',
        'retry_count'   => 'integer',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(CampaignContent::class, 'campaign_content_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeDueNow($query)
    {
        return $query->where('status', 'pending')
            ->where('scheduled_at', '<=', now());
    }
}
