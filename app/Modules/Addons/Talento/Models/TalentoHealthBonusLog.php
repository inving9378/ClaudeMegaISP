<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoHealthBonusLog extends Model
{
    protected $table = 'talento_health_bonus_log';
    public $timestamps = false;

    protected $fillable = [
        'work_order_id', 'tarea_id', 'colaborador_id', 'caja_ref',
        'baseline_power_dbm', 'client_power_dbm', 'loss_db', 'max_loss_db',
        'bonus_awarded', 'bonus_amount', 'power_source', 'skip_reason',
    ];

    protected $casts = [
        'baseline_power_dbm' => 'decimal:2',
        'client_power_dbm'   => 'decimal:2',
        'loss_db'            => 'decimal:2',
        'max_loss_db'        => 'decimal:2',
        'bonus_awarded'      => 'boolean',
        'bonus_amount'       => 'decimal:2',
        'checked_at'         => 'datetime',
    ];
}
