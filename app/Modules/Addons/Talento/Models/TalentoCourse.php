<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoCourse extends BaseModel
{
    protected $table = 'talento_courses';

    protected $fillable = [
        'title', 'description', 'department', 'order', 'active',
    ];

    protected $casts = ['active' => 'boolean'];

    public function materials()
    {
        return $this->hasMany(TalentoCourseMaterial::class, 'course_id')->orderBy('order');
    }

    public function exams()
    {
        return $this->hasMany(TalentoExam::class, 'course_id');
    }

    public function practicalEvaluations()
    {
        return $this->hasMany(TalentoPracticalEvaluation::class, 'course_id');
    }

    public function certifications()
    {
        return $this->hasMany(TalentoCertification::class, 'course_id');
    }
}
