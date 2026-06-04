<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoLevel extends BaseModel
{
    protected $table = 'talento_levels';

    protected $fillable = [
        'name', 'rank', 'required_certifications', 'base_salary', 'active',
    ];

    protected $casts = [
        'required_certifications' => 'array',
        'base_salary'             => 'decimal:2',
        'active'                  => 'boolean',
    ];

    public function colaboradores()
    {
        return $this->hasMany(TalentoColaborador::class, 'level_id');
    }

    public function assignments()
    {
        return $this->hasMany(TalentoLevelAssignment::class, 'level_id');
    }

    /**
     * Check if a collaborator's progress satisfies this level's requirements.
     * required_certifications formats supported:
     *   {"count": N}                    — needs at least N certifications from any course
     *   {"courses": [id1, id2, ...]}    — needs all of these specific courses certified
     *   {"count": N, "courses": [...]}  — needs N certifications INCLUDING specific courses
     */
    public function isSatisfiedBy(array $progress): bool
    {
        $req = $this->required_certifications ?? [];

        $certifiedCount    = $progress['certified_count'] ?? 0;
        $certifiedCourseIds = collect($progress['courses'] ?? [])
            ->where('certified', true)->pluck('course_id')->toArray();

        // Minimum count requirement
        if (isset($req['count']) && $certifiedCount < $req['count']) {
            return false;
        }

        // Specific courses requirement
        if (!empty($req['courses'])) {
            foreach ($req['courses'] as $courseId) {
                if (!in_array($courseId, $certifiedCourseIds)) {
                    return false;
                }
            }
        }

        return true;
    }
}
