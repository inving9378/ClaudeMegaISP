<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoCompensationRule extends BaseModel
{
    protected $table = 'talento_compensation_rules';

    protected $fillable = [
        'name', 'target_type', 'base_salary', 'period',
        'weekly_quota_units', 'monthly_bonus', 'conditions', 'active',
        // Item #121 — marco de compensación por KPI para roles no-técnicos (scaffolding, sin
        // montos/fórmulas reales todavía; ver comentario en la migración que las agrega).
        'variable_type', 'kpi_key', 'formula_config', 'valid_from', 'valid_until',
        'monthly_cutoff_day', 'clawback_days', 'clawback_requires_collection',
    ];

    protected $casts = [
        'base_salary'                  => 'decimal:2',
        'weekly_quota_units'           => 'integer',
        'monthly_bonus'                => 'array',
        'conditions'                   => 'array',
        'active'                       => 'boolean',
        'formula_config'               => 'array',
        'valid_from'                   => 'date',
        'valid_until'                  => 'date',
        'monthly_cutoff_day'           => 'integer',
        'clawback_days'                => 'integer',
        'clawback_requires_collection' => 'boolean',
    ];

    /** Días que cuenta una cuota "Diaria" en la semana de pago: lunes a sábado (decisión 2026-10-21). */
    public const DIAS_LABORALES_SEMANA = 6;

    /**
     * Cuota de la semana de pago a partir de la regla o de su copia en el historial. Una cuota
     * "Diaria" se multiplica por 6 días (lunes a sábado); cualquier otra se toma como semanal,
     * igual que antes. Punto único: liquidación, desglose del portal, tablero, puntuación y app.
     */
    public static function cuotaSemanal(array|object|null $regla): int
    {
        $regla = (array) $regla;
        $cuota = (int) ($regla['weekly_quota_units'] ?? 0);

        return ($regla['period'] ?? null) === 'daily' ? $cuota * self::DIAS_LABORALES_SEMANA : $cuota;
    }

    /**
     * Computed: value per unit = base_salary / cuota semanal.
     * Not stored — derived on demand.
     */
    public function getValuePerUnitAttribute(): float
    {
        $cuota = self::cuotaSemanal(['period' => $this->period, 'weekly_quota_units' => $this->weekly_quota_units]);
        if ($cuota <= 0) return 0.0;
        return round($this->base_salary / $cuota, 4);
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
