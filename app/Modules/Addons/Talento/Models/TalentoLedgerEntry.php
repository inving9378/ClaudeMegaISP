<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Immutable ledger. Never update or soft-delete entries.
 * To reverse: insert a counter-entry with opposite type.
 */
class TalentoLedgerEntry extends Model
{
    protected $table = 'talento_ledger_entries';
    public $timestamps = false;

    protected $fillable = [
        'colaborador_id', 'type', 'concept', 'amount',
        'reference_type', 'reference_id',
        'period_start', 'period_end', 'notes', 'created_by',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'period_start' => 'date',
        'period_end'   => 'date',
        'created_at'   => 'datetime',
    ];

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class);
    }

    public function reference()
    {
        return $this->morphTo('reference');
    }
}
