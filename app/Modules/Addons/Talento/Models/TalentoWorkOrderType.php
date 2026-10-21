<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoWorkOrderType extends BaseModel
{
    protected $table = 'talento_work_order_types';

    protected $fillable = ['name', 'category', 'points', 'is_billable', 'requires_validation', 'active'];

    protected $casts = [
        'is_billable'          => 'boolean',
        'requires_validation'  => 'boolean',
        'active'               => 'boolean',
        'points'               => 'integer',
    ];

    public function workOrders()
    {
        return $this->hasMany(TalentoWorkOrder::class, 'type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
