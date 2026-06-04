<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoCompensationRule extends BaseModel
{
    protected $table = 'talento_compensation_rules';

    protected $fillable = [
        'name', 'target_type', 'base_salary', 'period',
        'weekly_quota_units', 'monthly_bonus', 'conditions', 'active',
    ];

    protected $casts = [
        'base_salary'        => 'decimal:2',
        'weekly_quota_units' => 'integer',
        'monthly_bonus'      => 'array',
        'conditions'         => 'array',
        'active'             => 'boolean',
    ];

    /**
     * Computed: value per unit = base_salary / weekly_quota_units.
     * Not stored — derived on demand.
     */
    public function getValuePerUnitAttribute(): float
    {
        if ($this->weekly_quota_units <= 0) return 0.0;
        return round($this->base_salary / $this->weekly_quota_units, 4);
    }

    public function history()
    {
        return $this->hasMany(TalentoCompensationRuleHistory::class, 'rule_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
