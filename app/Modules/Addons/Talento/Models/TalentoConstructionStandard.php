<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoConstructionStandard extends BaseModel
{
    protected $table = 'talento_construction_standards';

    protected $fillable = [
        'name', 'type', 'ideal_value', 'reference_image_path', 'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function scopeActive($q)
    {
        return $q->where('active', true);
    }

    public function scopeOfType($q, string $type)
    {
        return $q->where('type', $type);
    }
}
