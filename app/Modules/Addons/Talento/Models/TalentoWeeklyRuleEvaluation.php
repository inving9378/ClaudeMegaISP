<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoWeeklyRuleEvaluation extends BaseModel
{
    protected $table = 'talento_weekly_rule_evaluations';

    protected $fillable = [
        'colaborador_id', 'rule_id', 'week_start', 'week_end',
        'prospects_count', 'sales_count', 'outcome',
        'penalty_type_id', 'penalty_amount', 'penalty_id',
        'confirmed_by', 'confirmed_at', 'bonus_amount_applied', 'evaluated_at',
    ];

    protected $casts = [
        'week_start'            => 'date',
        'week_end'               => 'date',
        'penalty_amount'         => 'decimal:2',
        'bonus_amount_applied'   => 'decimal:2',
        'confirmed_at'           => 'datetime',
        'evaluated_at'           => 'datetime',
    ];

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class, 'colaborador_id');
    }

    public function rule()
    {
        return $this->belongsTo(TalentoWeeklyRule::class, 'rule_id');
    }

    public function penalty()
    {
        return $this->belongsTo(TalentoPenalty::class, 'penalty_id');
    }

    public function penaltyType()
    {
        return $this->belongsTo(TalentoPenaltyType::class, 'penalty_type_id');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(TalentoColaborador::class, 'confirmed_by');
    }
}
