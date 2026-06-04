<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoCertification;
use App\Modules\Addons\Talento\Models\TalentoCourse;
use App\Modules\Addons\Talento\Models\TalentoExamAttempt;
use App\Modules\Addons\Talento\Models\TalentoPracticalEvaluation;

class CertificationService
{
    /**
     * Try to issue a certification for a collaborator on a course.
     * Requires: a passed exam attempt AND an approved practical evaluation.
     * Idempotent: skips if active certification already exists.
     * Returns the certification (new or existing) or null if conditions not met.
     */
    public function tryIssue(int $colaboradorId, int $courseId): ?TalentoCertification
    {
        // Already has an active cert → return it
        $existing = TalentoCertification::where('colaborador_id', $colaboradorId)
            ->where('course_id', $courseId)
            ->where('status', 'active')
            ->first();
        if ($existing) return $existing;

        $course = TalentoCourse::with('exams')->findOrFail($courseId);

        // Need a passed exam attempt
        $exam = $course->exams()->where('active', true)->first();
        $passedAttempt = $exam
            ? TalentoExamAttempt::where('exam_id', $exam->id)
                ->where('colaborador_id', $colaboradorId)
                ->where('passed', true)
                ->orderByDesc('score')
                ->first()
            : null;

        if (!$passedAttempt) return null;

        // Need an approved practical evaluation
        $practical = TalentoPracticalEvaluation::where('course_id', $courseId)
            ->where('colaborador_id', $colaboradorId)
            ->where('result', 'approved')
            ->latest('created_at')
            ->first();

        if (!$practical) return null;

        return TalentoCertification::create([
            'colaborador_id'          => $colaboradorId,
            'course_id'               => $courseId,
            'exam_attempt_id'         => $passedAttempt->id,
            'practical_evaluation_id' => $practical->id,
            'badge_label'             => "Certificado: {$course->title}",
            'certified_at'            => now(),
            'status'                  => 'active',
            'created_by'              => auth()->id(),
            'created_at'              => now(),
        ]);
    }

    public function revoke(TalentoCertification $cert): TalentoCertification
    {
        $cert->update(['status' => 'revoked']);
        return $cert->fresh();
    }

    /**
     * Return the academy progress for a collaborator (consumed by LevelService).
     * Mirrors TalentoAcademyController::progressForColaborador().
     */
    public function progressForColaborador(int $colaboradorId): array
    {
        $certs = TalentoCertification::where('colaborador_id', $colaboradorId)
            ->where('status', 'active')->count();
        $total = TalentoCourse::where('active', true)->count();

        $courses = TalentoCourse::with(['exams' => fn($q) => $q->where('active', true)])
            ->where('active', true)
            ->get()
            ->map(function ($c) use ($colaboradorId) {
                $examPassed = $c->exams->first()
                    ? TalentoExamAttempt::where('exam_id', $c->exams->first()->id)
                        ->where('colaborador_id', $colaboradorId)->where('passed', true)->exists()
                    : false;
                $practicalOk = TalentoPracticalEvaluation::where('course_id', $c->id)
                    ->where('colaborador_id', $colaboradorId)->where('result', 'approved')->exists();
                $certified = TalentoCertification::where('course_id', $c->id)
                    ->where('colaborador_id', $colaboradorId)->where('status', 'active')->exists();
                return [
                    'course_id'    => $c->id,
                    'title'        => $c->title,
                    'exam_passed'  => $examPassed,
                    'practical_ok' => $practicalOk,
                    'certified'    => $certified,
                ];
            })->toArray();

        return [
            'certified_count' => $certs,
            'total_courses'   => $total,
            'completion_pct'  => $total > 0 ? round(($certs / $total) * 100, 1) : 0,
            'courses'         => $courses,
        ];
    }
}
