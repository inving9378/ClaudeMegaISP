<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

/** J3: ajuste que corrige una semana ya cerrada y se paga en la semana abierta (target_*). */
class TalentoPeriodAdjustment extends Model
{
    protected $table = 'talento_period_adjustments';

    protected $guarded = [];

    protected $casts = [
        'original_period_start' => 'date',
        'original_period_end'   => 'date',
        'target_period_start'   => 'date',
        'target_period_end'     => 'date',
        'points_delta'          => 'decimal:2',
        'amount_delta'          => 'decimal:2',
    ];
}
