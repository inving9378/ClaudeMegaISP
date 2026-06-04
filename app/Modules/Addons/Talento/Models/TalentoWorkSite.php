<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoWorkSite extends BaseModel
{
    protected $table = 'talento_work_sites';

    protected $fillable = ['name', 'type', 'latitude', 'longitude', 'radius_m', 'active'];

    protected $casts = [
        'latitude'  => 'decimal:7',
        'longitude' => 'decimal:7',
        'radius_m'  => 'integer',
        'active'    => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
