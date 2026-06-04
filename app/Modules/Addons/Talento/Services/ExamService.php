<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoExam;
use App\Modules\Addons\Talento\Models\TalentoExamAttempt;
use App\Modules\Addons\Talento\Models\TalentoExamQuestion;

class ExamService
{
    /**
     * Grade a submitted exam and return an attempt record.
     *
     * @param  int   $examId
     * @param  int   $colaboradorId
     * @param  array $answers  {question_id: [selected_indices]}
     */
    public function grade(int $examId, int $colaboradorId, array $answers): TalentoExamAttempt
    {
        $exam      = TalentoExam::with('questions')->findOrFail($examId);
        $questions = $exam->questions->keyBy('id');

        $earned = 0;
        $total  = 0;

        foreach ($questions as $q) {
            $total += $q->points;
            $submitted = array_map('intval', (array)($answers[$q->id] ?? []));
            $correct   = array_map('intval', $q->correct_answer);

            sort($submitted);
            sort($correct);

            if ($submitted === $correct) {
                $earned += $q->points;
            }
        }

        $score  = $total > 0 ? (int)round(($earned / $total) * 100) : 0;
        $passed = $score >= $exam->passing_score;

        return TalentoExamAttempt::create([
            'exam_id'        => $examId,
            'colaborador_id' => $colaboradorId,
            'score'          => $score,
            'passed'         => $passed,
            'answers'        => $answers,
            'attempted_at'   => now(),
        ]);
    }

    /**
     * Return the best (highest score) passed attempt for this collaborator+exam,
     * or null if never passed.
     */
    public function bestPassed(int $examId, int $colaboradorId): ?TalentoExamAttempt
    {
        return TalentoExamAttempt::where('exam_id', $examId)
            ->where('colaborador_id', $colaboradorId)
            ->where('passed', true)
            ->orderByDesc('score')
            ->first();
    }
}
