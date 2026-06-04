<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoCourseMaterial extends Model
{
    protected $table = 'talento_course_materials';

    public $timestamps = false;

    protected $fillable = [
        'course_id', 'type', 'title', 'content', 'video_url', 'file_path',
        'reference_standard_id', 'reference_penalty_type_id', 'order', 'created_by',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public function course()
    {
        return $this->belongsTo(TalentoCourse::class, 'course_id');
    }

    public function referenceStandard()
    {
        return $this->belongsTo(TalentoConstructionStandard::class, 'reference_standard_id');
    }

    public function referencePenaltyType()
    {
        return $this->belongsTo(TalentoPenaltyType::class, 'reference_penalty_type_id');
    }
}
