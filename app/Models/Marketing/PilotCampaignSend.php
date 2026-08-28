<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PilotCampaignSend extends Model
{
    protected $table = 'marketing_pilot_campaign_sends';

    protected $fillable = [
        'pilot_campaign_id', 'email', 'name', 'variant', 'token', 'status',
        'sent_at', 'opened_at', 'clicked_at', 'converted_at', 'error',
    ];

    protected $casts = [
        'sent_at'      => 'datetime',
        'opened_at'    => 'datetime',
        'clicked_at'   => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PilotCampaign::class, 'pilot_campaign_id');
    }
}
