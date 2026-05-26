<?php

namespace App\Modules\Addons\Marketing\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends BaseModel
{
    use SoftDeletes;

    protected $table = 'marketing_leads';

    protected $fillable = [
        'campaign_id', 'channel', 'contact_identifier', 'contact_name',
        'conversation', 'qualification_score', 'qualification_notes',
        'status', 'client_id', 'scheduling_id', 'assigned_to',
    ];

    protected $casts = [
        'conversation'        => 'array',
        'qualification_score' => 'integer',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }

    public function scopeQualified($query)
    {
        return $query->where('status', 'qualified');
    }

    public function getScoreLabelAttribute(): string
    {
        $score = $this->qualification_score ?? 0;
        return match (true) {
            $score >= 80 => 'Hot',
            $score >= 50 => 'Warm',
            $score >= 20 => 'Cold',
            default      => 'Sin calificar',
        };
    }

    public function getScoreColorAttribute(): string
    {
        $score = $this->qualification_score ?? 0;
        return match (true) {
            $score >= 80 => 'negative',
            $score >= 50 => 'warning',
            $score >= 20 => 'info',
            default      => 'grey',
        };
    }
}
