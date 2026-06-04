<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoSettlement extends BaseModel
{
    protected $table = 'talento_settlements';

    protected $fillable = [
        'colaborador_id', 'settlement_date', 'status',
        'gross_credits', 'gross_debits', 'net_settlement',
        'detail', 'closed_at', 'created_by',
    ];

    protected $casts = [
        'settlement_date' => 'date',
        'gross_credits'   => 'decimal:2',
        'gross_debits'    => 'decimal:2',
        'net_settlement'  => 'decimal:2',
        'detail'          => 'array',
        'closed_at'       => 'datetime',
    ];

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class, 'colaborador_id');
    }

    public function items()
    {
        return $this->hasMany(TalentoSettlementItem::class, 'settlement_id');
    }
}
