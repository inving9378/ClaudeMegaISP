<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoExam extends BaseModel
{
    protected $table = 'talento_exams';

    protected $fillable = ['course_id', 'title', 'passing_score', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function course()
    {
        return $this->belongsTo(TalentoCourse::class, 'course_id');
    }

    public function questions()
    {
        return $this->hasMany(TalentoExamQuestion::class, 'exam_id')->orderBy('order');
    }

    public function attempts()
    {
        return $this->hasMany(TalentoExamAttempt::class, 'exam_id');
    }
}
