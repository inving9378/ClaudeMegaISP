<?php

namespace App\Modules\Addons\Marketing\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignContent extends BaseModel
{
    protected $table = 'campaign_contents';

    protected $fillable = [
        'campaign_id', 'content_type', 'copy_text', 'image_url',
        'image_prompt', 'ia_generated', 'variation_index', 'status', 'approved_by',
    ];

    protected $casts = [
        'ia_generated'    => 'boolean',
        'variation_index' => 'integer',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(CampaignSchedule::class, 'campaign_content_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
