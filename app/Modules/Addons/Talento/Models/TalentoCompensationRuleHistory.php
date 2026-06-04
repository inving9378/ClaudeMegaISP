<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoCompensationRuleHistory extends Model
{
    protected $table = 'talento_compensation_rule_history';
    public $timestamps = false;

    protected $fillable = ['colaborador_id', 'rule_id', 'data', 'assigned_at'];

    protected $casts = [
        'data'        => 'array',
        'assigned_at' => 'datetime',
        'created_at'  => 'datetime',
    ];

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class);
    }

    public function rule()
    {
        return $this->belongsTo(TalentoCompensationRule::class, 'rule_id');
    }
}
