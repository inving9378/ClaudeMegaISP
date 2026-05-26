<?php

namespace App\Modules\Addons\Marketing\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends BaseModel
{
    use SoftDeletes;

    protected $table = 'marketing_campaigns';

    protected $fillable = [
        'title', 'description', 'target_zone', 'target_plan_id',
        'status', 'channel', 'daily_limit', 'start_date', 'end_date',
        'approved_by', 'approved_at',
    ];

    protected $casts = [
        'channel'     => 'array',
        'start_date'  => 'date',
        'end_date'    => 'date',
        'approved_at' => 'datetime',
    ];

    public function contents(): HasMany
    {
        return $this->hasMany(CampaignContent::class, 'campaign_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(CampaignSchedule::class, 'campaign_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'campaign_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'pending_approval']);
    }
}
