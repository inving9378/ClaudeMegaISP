<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoPenalty extends Model
{
    protected $table = 'talento_penalties';

    public $timestamps = false;

    protected $fillable = [
        'colaborador_id', 'penalty_type_id', 'amount', 'applied_by',
        'evidence_photo_path', 'captured_lat', 'captured_lng', 'captured_in_app',
        'status', 'notes', 'created_by', 'created_at',
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'captured_in_app' => 'boolean',
        'created_at'      => 'datetime',
    ];

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class, 'colaborador_id');
    }

    public function penaltyType()
    {
        return $this->belongsTo(TalentoPenaltyType::class, 'penalty_type_id');
    }

    public function appliedByColaborador()
    {
        return $this->belongsTo(TalentoColaborador::class, 'applied_by');
    }

    public function appeal()
    {
        return $this->hasOne(TalentoPenaltyAppeal::class, 'penalty_id');
    }

    public function ledgerEntries()
    {
        return $this->morphMany(TalentoLedgerEntry::class, 'reference');
    }
}
