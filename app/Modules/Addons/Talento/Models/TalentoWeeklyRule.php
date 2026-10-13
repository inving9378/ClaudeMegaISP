<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoWeeklyRule extends BaseModel
{
    protected $table = 'talento_weekly_rules';

    protected $fillable = [
        'name', 'roles', 'metric_type', 'min_prospects', 'min_sales',
        'penalty_type_id', 'bonus_prospects_threshold', 'bonus_sales_threshold',
        'bonus_amount', 'active',
    ];

    protected $casts = [
        'roles'        => 'array',
        'bonus_amount' => 'decimal:2',
        'active'       => 'boolean',
    ];

    public function penaltyType()
    {
        return $this->belongsTo(TalentoPenaltyType::class, 'penalty_type_id');
    }

    public function scopeActive($q)
    {
        return $q->where('active', true);
    }

    /**
     * Normaliza un arreglo de nombres de rol a la forma canónica usada
     * para comparar combinaciones (orden alfabético, sin duplicados).
     */
    public static function normalizeRoles(array $roles): array
    {
        $roles = array_values(array_unique($roles));
        sort($roles);
        return $roles;
    }
}
