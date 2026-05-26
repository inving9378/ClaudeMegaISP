<?php

namespace App\Modules\Addons\Marketing\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingTemplate extends BaseModel
{
    use SoftDeletes;

    protected $table = 'marketing_templates';

    protected $fillable = [
        'name', 'description', 'channel', 'template_type',
        'system_prompt', 'base_copy', 'variables', 'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }
}
