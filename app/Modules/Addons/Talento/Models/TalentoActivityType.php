<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoActivityType extends BaseModel
{
    protected $table = 'talento_activity_types';

    protected $fillable = ['name', 'unit', 'points_per_unit', 'money_per_unit', 'active'];

    protected $casts = [
        'points_per_unit' => 'decimal:4',
        'money_per_unit'  => 'decimal:4',
        'active'          => 'boolean',
    ];

    public function requiredLevel()
    {
        return $this->belongsTo(TalentoLevel::class, 'required_level_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
