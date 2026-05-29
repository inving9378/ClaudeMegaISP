<?php

namespace App\Models\Marketing;

use App\Models\BaseModel;

class MarketingNiche extends BaseModel
{
    protected $table = 'marketing_niches';

    protected $fillable = [
        'company_id', 'slug', 'name', 'description',
        'motivators', 'pain_points', 'objections', 'vocabulary',
        'emotional_tone', 'music_mood', 'broll_tags', 'voice_style',
        'preferred_voice_id', 'active', 'display_order',
    ];

    protected $casts = [
        'motivators'  => 'array',
        'pain_points' => 'array',
        'objections'  => 'array',
        'vocabulary'  => 'array',
        'broll_tags'  => 'array',
        'active'      => 'boolean',
    ];

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
