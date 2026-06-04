<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoLoan extends BaseModel
{
    protected $table = 'talento_loans';

    protected $fillable = [
        'colaborador_id', 'amount', 'balance', 'repayment_weekly',
        'reason', 'authorized', 'authorized_by', 'authorized_at',
        'status', 'created_by',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'balance'          => 'decimal:2',
        'repayment_weekly' => 'decimal:2',
        'authorized'       => 'boolean',
        'authorized_at'    => 'datetime',
    ];

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class, 'colaborador_id');
    }

    public function authorizedByUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'authorized_by');
    }

    public function ledgerEntries()
    {
        return $this->morphMany(TalentoLedgerEntry::class, 'reference');
    }

    /** Cuántas semanas restan para saldarlo al ritmo actual */
    public function weeksRemaining(): ?int
    {
        if ((float)($this->repayment_weekly ?? 0) <= 0) return null;
        $bal = (float)$this->balance;
        if ($bal <= 0) return 0;
        return (int)ceil($bal / (float)$this->repayment_weekly);
    }
}
