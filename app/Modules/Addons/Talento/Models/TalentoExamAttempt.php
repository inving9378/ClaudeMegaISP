<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoExamAttempt extends Model
{
    protected $table = 'talento_exam_attempts';

    public $timestamps = false;

    protected $fillable = [
        'exam_id', 'colaborador_id', 'score', 'passed', 'answers', 'attempted_at',
    ];

    protected $casts = [
        'passed'       => 'boolean',
        'answers'      => 'array',
        'attempted_at' => 'datetime',
    ];

    public function exam()
    {
        return $this->belongsTo(TalentoExam::class, 'exam_id');
    }

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class, 'colaborador_id');
    }
}
