<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoCertification extends Model
{
    protected $table = 'talento_certifications';

    public $timestamps = false;

    protected $fillable = [
        'colaborador_id', 'course_id', 'exam_attempt_id', 'practical_evaluation_id',
        'badge_label', 'certified_at', 'status', 'created_by', 'created_at',
    ];

    protected $casts = [
        'certified_at' => 'datetime',
        'created_at'   => 'datetime',
    ];

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class, 'colaborador_id');
    }

    public function course()
    {
        return $this->belongsTo(TalentoCourse::class, 'course_id');
    }

    public function examAttempt()
    {
        return $this->belongsTo(TalentoExamAttempt::class, 'exam_attempt_id');
    }

    public function practicalEvaluation()
    {
        return $this->belongsTo(TalentoPracticalEvaluation::class, 'practical_evaluation_id');
    }
}
