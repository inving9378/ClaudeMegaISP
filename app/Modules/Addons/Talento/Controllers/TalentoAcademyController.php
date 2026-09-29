<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoCertification;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoCourse;
use App\Modules\Addons\Talento\Models\TalentoCourseMaterial;
use App\Modules\Addons\Talento\Models\TalentoExam;
use App\Modules\Addons\Talento\Models\TalentoExamQuestion;
use App\Modules\Addons\Talento\Models\TalentoPracticalEvaluation;
use App\Modules\Addons\Talento\Services\CertificationService;
use App\Modules\Addons\Talento\Services\ExamService;
use App\Modules\Addons\Talento\Support\Actor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * David (29-sep), verificando "Academia": el controller no tenía NINGÚN
 * authorize()/scoping salvo serveEvidencePractical() — 8 métodos de
 * escritura sin protección (crear/editar cursos, materiales, exámenes,
 * evaluaciones prácticas, revocar certificaciones) + 2 de lectura sin
 * scoping (certificaciones/progreso de CUALQUIER colaborador). El gap más
 * grave YA ERA explotable hoy: talento.academy.view (que TECNICO/
 * TECNICO_PLANTA/TECNICO_INSTALADOR tienen directo, para poder VER el
 * catálogo) cubre en config/route_permission.php las mismas rutas
 * '/talento/api/courses' y '/talento/api/courses/{id}' que storeCourse()/
 * updateCourse() — el middleware es agnóstico al método HTTP (GET/POST/PUT
 * comparten el mismo patrón), así que CUALQUIER técnico podía crear o
 * editar cursos reales sin que el controller lo frenara. Mismo patrón que
 * TalentoQualityController (item de esta sesión, 28-sep) — se cierran
 * los 10 huecos de una vez, no solo el explotable hoy.
 */
class TalentoAcademyController extends Controller
{
    public function __construct(
        private ExamService          $examSvc,
        private CertificationService $certSvc
    ) {}

    /**
     * Ver progreso/certificaciones de $colaboradorId: uno mismo, su
     * supervisor directo, o staff (talento.employees.view — NO
     * talento.academy.view, que también la tiene TECNICO directo para
     * ver SU PROPIO catálogo/progreso y no distingue "staff que puede ver
     * a cualquiera" de "técnico viendo lo suyo").
     */
    private function puedeVerAcademiaDe($colaboradorId): bool
    {
        if (auth()->user()->can('talento.employees.view')) {
            return true;
        }
        $miPropioColaborador = Actor::for(auth()->user())->talento();
        if (! $miPropioColaborador) {
            return false;
        }

        return (string) $miPropioColaborador->id === (string) $colaboradorId
            || $miPropioColaborador->subordinados()->where('id', $colaboradorId)->exists();
    }

    // ── Web view ───────────────────────────────────────────────────────────

    public function index()
    {
        return view('addon-talento::talento.academia');
    }

    // ── Courses ────────────────────────────────────────────────────────────

    public function courses(Request $request)
    {
        $q = TalentoCourse::query();
        if ($request->boolean('active_only', false)) $q->where('active', true);
        if ($request->filled('department'))           $q->where('department', $request->department);
        return response()->json($q->orderBy('order')->orderBy('title')->get());
    }

    public function showCourse(int $id)
    {
        $course = TalentoCourse::with([
            'materials.referenceStandard',
            'materials.referencePenaltyType',
            'exams' => fn($q) => $q->where('active', true),
        ])->findOrFail($id);

        return response()->json($course);
    }

    public function storeCourse(Request $request)
    {
        $this->authorize('talento.academy.manage');

        $data = $request->validate([
            'title'       => 'required|string|max:200',
            'description' => 'nullable|string',
            'department'  => 'nullable|string|max:80',
            'order'       => 'integer|min:0',
            'active'      => 'boolean',
        ]);
        return response()->json(TalentoCourse::create($data), 201);
    }

    public function updateCourse(Request $request, int $id)
    {
        $this->authorize('talento.academy.manage');

        $course = TalentoCourse::findOrFail($id);
        $data   = $request->validate([
            'title'       => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'department'  => 'nullable|string|max:80',
            'order'       => 'integer|min:0',
            'active'      => 'boolean',
        ]);
        $course->update($data);
        return response()->json($course);
    }

    // ── Materials ──────────────────────────────────────────────────────────

    public function storeMaterial(Request $request, int $courseId)
    {
        $this->authorize('talento.academy.manage');

        TalentoCourse::findOrFail($courseId);
        $data = $request->validate([
            'type'                     => 'required|in:text,video,reference',
            'title'                    => 'nullable|string|max:200',
            'content'                  => 'nullable|string',
            'video_url'                => 'nullable|url|max:512',
            'order'                    => 'integer|min:0',
            'reference_standard_id'    => 'nullable|integer|exists:talento_construction_standards,id',
            'reference_penalty_type_id'=> 'nullable|integer|exists:talento_penalty_types,id',
        ]);

        $filePath = null;
        if ($request->hasFile('file')) {
            $request->validate(['file' => 'file|mimes:pdf,jpg,jpeg,png|max:8192']);
            $filePath = $request->file('file')->store("talento/academy/courses/{$courseId}", 'public');
        }

        $material = TalentoCourseMaterial::create(array_merge($data, [
            'course_id'  => $courseId,
            'file_path'  => $filePath,
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]));

        return response()->json($material->load(['referenceStandard', 'referencePenaltyType']), 201);
    }

    public function destroyMaterial(int $id)
    {
        $this->authorize('talento.academy.manage');

        TalentoCourseMaterial::findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    // ── Exams ──────────────────────────────────────────────────────────────

    public function storeExam(Request $request, int $courseId)
    {
        $this->authorize('talento.academy.manage');

        TalentoCourse::findOrFail($courseId);
        $data = $request->validate([
            'title'         => 'required|string|max:200',
            'passing_score' => 'integer|min:1|max:100',
            'active'        => 'boolean',
        ]);
        $exam = TalentoExam::create(array_merge($data, ['course_id' => $courseId, 'created_by' => auth()->id()]));
        return response()->json($exam, 201);
    }

    public function storeQuestion(Request $request, int $examId)
    {
        $this->authorize('talento.academy.manage');

        TalentoExam::findOrFail($examId);
        $data = $request->validate([
            'question'       => 'required|string',
            'type'           => 'required|in:single,multiple,true_false',
            'options'        => 'required|array|min:2',
            'correct_answer' => 'required|array|min:1',
            'points'         => 'integer|min:1',
            'order'          => 'integer|min:0',
        ]);
        $q = TalentoExamQuestion::create(array_merge($data, [
            'exam_id'    => $examId,
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]));
        return response()->json($q->makeVisible('correct_answer'), 201);
    }

    public function examForStudent(int $examId)
    {
        // Returns exam WITH questions but WITHOUT correct_answer
        $exam = TalentoExam::with('questions')->findOrFail($examId);
        return response()->json($exam);
    }

    public function submitExam(Request $request, int $examId)
    {
        $data = $request->validate(['answers' => 'required|array']);

        $colaborador = TalentoColaborador::where('user_id', auth()->id())->first();
        if (!$colaborador) {
            return response()->json(['error' => 'No tienes perfil de colaborador.'], 422);
        }

        $attempt  = $this->examSvc->grade($examId, $colaborador->id, $data['answers']);
        $exam     = TalentoExam::findOrFail($examId);

        // Try to issue certification if passed
        $cert = null;
        if ($attempt->passed) {
            $cert = $this->certSvc->tryIssue($colaborador->id, $exam->course_id);
        }

        return response()->json([
            'attempt_id'    => $attempt->id,
            'score'         => $attempt->score,
            'passed'        => $attempt->passed,
            'passing_score' => $exam->passing_score,
            'certification' => $cert,
        ]);
    }

    public function myAttempts(int $examId)
    {
        $colaborador = TalentoColaborador::where('user_id', auth()->id())->first();
        if (!$colaborador) return response()->json([]);

        $attempts = \App\Modules\Addons\Talento\Models\TalentoExamAttempt::where('exam_id', $examId)
            ->where('colaborador_id', $colaborador->id)
            ->orderByDesc('attempted_at')
            ->get(['id', 'score', 'passed', 'attempted_at']);

        return response()->json($attempts);
    }

    // ── Practical evaluations ──────────────────────────────────────────────

    public function storePractical(Request $request, int $courseId)
    {
        // Sin esto, CUALQUIERA con talento.academy.view (todo técnico)
        // podía aprobarse a sí mismo o a cualquier colaborador y emitir
        // una certificación real — la evaluación práctica la hace un
        // evaluador autorizado, nunca autoservicio.
        $this->authorize('talento.academy.evaluate');

        TalentoCourse::findOrFail($courseId);
        $data = $request->validate([
            'colaborador_id'  => 'required|integer|exists:talento_colaboradores,id',
            'result'          => 'required|in:approved,rejected',
            'captured_lat'    => 'nullable|numeric',
            'captured_lng'    => 'nullable|numeric',
            'captured_in_app' => 'boolean',
            'notes'           => 'nullable|string|max:1000',
        ]);

        $evaluator = TalentoColaborador::where('user_id', auth()->id())->first();
        if (!$evaluator) {
            return response()->json(['error' => 'No tienes perfil de colaborador.'], 422);
        }

        $evidencePath = null;
        if ($request->hasFile('evidence')) {
            $request->validate(['evidence' => 'image|max:8192']);
            $evidencePath = $request->file('evidence')->store(
                'talento/academy/practical/' . now()->format('Y/m'), 'local'
            );
        }

        $eval = TalentoPracticalEvaluation::create(array_merge($data, [
            'course_id'     => $courseId,
            'evaluator_id'  => $evaluator->id,
            'evidence_path' => $evidencePath,
            'evaluated_at'  => now(),
            'created_by'    => auth()->id(),
            'created_at'    => now(),
        ]));

        $cert = null;
        if ($data['result'] === 'approved') {
            $cert = $this->certSvc->tryIssue($data['colaborador_id'], $courseId);
        }

        return response()->json([
            'evaluation'    => $eval->load(['colaborador.user', 'evaluator.user']),
            'certification' => $cert,
        ], 201);
    }

    public function serveEvidencePractical(int $id)
    {
        $this->authorize('talento.academy.view');
        $eval = TalentoPracticalEvaluation::findOrFail($id);
        if (!$eval->evidence_path || !Storage::disk('local')->exists($eval->evidence_path)) abort(404);
        return response()->file(Storage::disk('local')->path($eval->evidence_path));
    }

    // ── Certifications ────────────────────────────────────────────────────

    public function certificationsForColaborador(int $colaboradorId)
    {
        abort_unless($this->puedeVerAcademiaDe($colaboradorId), 403);

        return response()->json(
            TalentoCertification::with(['course:id,title', 'examAttempt:id,score,attempted_at'])
                ->where('colaborador_id', $colaboradorId)
                ->orderByDesc('certified_at')
                ->get()
        );
    }

    public function myCertifications()
    {
        $colaborador = TalentoColaborador::where('user_id', auth()->id())->first();
        if (!$colaborador) return response()->json([]);
        return $this->certificationsForColaborador($colaborador->id);
    }

    public function revokeCertification(int $id)
    {
        $this->authorize('talento.academy.evaluate');

        $cert = TalentoCertification::findOrFail($id);
        return response()->json($this->certSvc->revoke($cert));
    }

    // ── Progress snapshot (used by Fase 7b for levels) ────────────────────
    public function progressForColaborador(int $colaboradorId)
    {
        abort_unless($this->puedeVerAcademiaDe($colaboradorId), 403);

        $certs  = TalentoCertification::where('colaborador_id', $colaboradorId)->where('status', 'active')->count();
        $total  = TalentoCourse::where('active', true)->count();
        $courses = TalentoCourse::with(['exams' => fn($q) => $q->where('active', true)])
            ->where('active', true)
            ->get()
            ->map(function ($c) use ($colaboradorId) {
                $examPassed = $c->exams->first()
                    ? \App\Modules\Addons\Talento\Models\TalentoExamAttempt::where('exam_id', $c->exams->first()->id)
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
            });

        return response()->json([
            'certified_count' => $certs,
            'total_courses'   => $total,
            'completion_pct'  => $total > 0 ? round(($certs / $total) * 100, 1) : 0,
            'courses'         => $courses,
        ]);
    }
}
