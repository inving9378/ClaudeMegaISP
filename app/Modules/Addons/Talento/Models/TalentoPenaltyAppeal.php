<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoPenaltyAppeal extends Model
{
    protected $table = 'talento_penalty_appeals';

    public $timestamps = false;

    protected $fillable = [
        'penalty_id', 'appealed_by', 'reason', 'evidence_path',
        'reviewed_by', 'decision', 'decision_notes',
        'created_at', 'resolved_at',
    ];

    protected $casts = [
        'created_at'  => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function penalty()
    {
        return $this->belongsTo(TalentoPenalty::class, 'penalty_id');
    }

    public function appealedByColaborador()
    {
        return $this->belongsTo(TalentoColaborador::class, 'appealed_by');
    }

    public function reviewedByColaborador()
    {
        return $this->belongsTo(TalentoColaborador::class, 'reviewed_by');
    }
}
