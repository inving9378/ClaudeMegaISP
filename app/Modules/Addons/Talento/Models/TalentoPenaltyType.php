<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoPenaltyType extends BaseModel
{
    protected $table = 'talento_penalty_types';

    protected $fillable = [
        'name', 'category', 'penalty_kind', 'amount',
        'reference_image_path', 'active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function scopeActive($q)
    {
        return $q->where('active', true);
    }
}
