<?php

namespace App\Modules\Addons\WarRoom\Models;

use Illuminate\Database\Eloquent\Model;

class SectionTemplate extends Model
{
    protected $table = 'warroom_section_templates';

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true)->orderBy('order_position');
    }
}
