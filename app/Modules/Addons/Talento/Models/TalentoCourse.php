<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoCourse extends BaseModel
{
    protected $table = 'talento_courses';

    protected $fillable = [
        'title', 'description', 'department', 'roles', 'order', 'active',
    ];

    protected $casts = ['active' => 'boolean', 'roles' => 'array'];

    protected $appends = ['para'];

    // Para quién es el curso (2026-10-24): `roles` vacío = general, para todos; si trae roles, solo
    // para quien tenga alguno de ellos.
    public function scopeParaRoles($query, iterable $roles)
    {
        $roles = collect($roles)->values();

        return $query->where(function ($q) use ($roles) {
            $q->whereNull('roles')->orWhereJsonLength('roles', 0);
            foreach ($roles as $rol) {
                $q->orWhereJsonContains('roles', $rol);
            }
        });
    }

    public function esParaRoles(iterable $roles): bool
    {
        return empty($this->roles) || collect($roles)->intersect($this->roles)->isNotEmpty();
    }

    public function getParaAttribute(): string
    {
        return empty($this->roles)
            ? 'general'
            : collect($this->roles)->map(fn ($r) => TalentoColaborador::nombrePuesto($r))->join(', ');
    }

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
