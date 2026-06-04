<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoLiquidation extends BaseModel
{
    protected $table = 'talento_liquidations';

    protected $fillable = [
        'colaborador_id', 'period_start', 'period_end',
        'total_units', 'base_paid', 'overproduction_paid',
        'other_credits', 'other_debits', 'gross_pay',
        'status', 'closed_at',
    ];

    protected $casts = [
        'period_start'        => 'date',
        'period_end'          => 'date',
        'base_paid'           => 'decimal:2',
        'overproduction_paid' => 'decimal:2',
        'other_credits'       => 'decimal:2',
        'other_debits'        => 'decimal:2',
        'gross_pay'           => 'decimal:2',
        'closed_at'           => 'datetime',
        'total_units'         => 'integer',
    ];

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class);
    }

    public function ledgerEntries()
    {
        return TalentoLedgerEntry::where('colaborador_id', $this->colaborador_id)
            ->where('period_start', $this->period_start)
            ->where('period_end', $this->period_end);
    }
}
