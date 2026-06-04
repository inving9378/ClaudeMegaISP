<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoExamQuestion extends Model
{
    protected $table = 'talento_exam_questions';

    public $timestamps = false;

    protected $fillable = [
        'exam_id', 'question', 'type', 'options', 'correct_answer', 'points', 'order', 'created_by',
    ];

    protected $casts = [
        'options'        => 'array',
        'correct_answer' => 'array',
        'created_at'     => 'datetime',
    ];

    // Never expose correct_answer to the collaborator
    protected $hidden = ['correct_answer'];

    public function exam()
    {
        return $this->belongsTo(TalentoExam::class, 'exam_id');
    }
}
