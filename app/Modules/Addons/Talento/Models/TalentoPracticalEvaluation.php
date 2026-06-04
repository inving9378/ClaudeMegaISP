<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoPracticalEvaluation extends Model
{
    protected $table = 'talento_practical_evaluations';

    public $timestamps = false;

    protected $fillable = [
        'course_id', 'colaborador_id', 'evaluator_id',
        'evidence_path', 'captured_in_app', 'captured_lat', 'captured_lng',
        'result', 'notes', 'evaluated_at', 'created_by', 'created_at',
    ];

    protected $casts = [
        'captured_in_app' => 'boolean',
        'evaluated_at'    => 'datetime',
        'created_at'      => 'datetime',
    ];

    protected $hidden = ['evidence_path'];

    public function course()
    {
        return $this->belongsTo(TalentoCourse::class, 'course_id');
    }

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class, 'colaborador_id');
    }

    public function evaluator()
    {
        return $this->belongsTo(TalentoColaborador::class, 'evaluator_id');
    }
}
