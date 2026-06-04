<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoFund extends BaseModel
{
    protected $table = 'talento_funds';

    protected $fillable = [
        'colaborador_id', 'purpose', 'target_amount', 'accumulated',
        'weekly_deduction', 'status', 'authorized', 'authorized_at',
        'authorized_by', 'credential_id', 'notes', 'created_by',
    ];

    protected $casts = [
        'target_amount'    => 'decimal:2',
        'accumulated'      => 'decimal:2',
        'weekly_deduction' => 'decimal:2',
        'authorized'       => 'boolean',
        'authorized_at'    => 'datetime',
    ];

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class, 'colaborador_id');
    }

    public function credential()
    {
        return $this->belongsTo(TalentoCredential::class, 'credential_id');
    }

    public function authorizedByUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'authorized_by');
    }

    /** Porcentaje acumulado respecto al objetivo. */
    public function progressPct(): float
    {
        if ((float)$this->target_amount <= 0) return 0.0;
        return round(min(100, ((float)$this->accumulated / (float)$this->target_amount) * 100), 1);
    }

    /** Semanas restantes estimadas para completar el fondo. */
    public function weeksRemaining(): ?int
    {
        if ((float)$this->weekly_deduction <= 0) return null;
        $remaining = (float)$this->target_amount - (float)$this->accumulated;
        if ($remaining <= 0) return 0;
        return (int) ceil($remaining / (float)$this->weekly_deduction);
    }
}
