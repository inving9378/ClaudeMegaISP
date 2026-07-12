<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoPenalty;
use App\Modules\Addons\Talento\Models\TalentoPenaltyAppeal;
use App\Modules\Addons\Talento\Models\TalentoPenaltyType;
use App\Modules\Addons\Talento\Services\PenaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TalentoPenaltyController extends Controller
{
    public function __construct(private PenaltyService $svc) {}

    // ── Web views ──────────────────────────────────────────────────────────

    public function index()
    {
        return view('addon-talento::talento.penalizaciones');
    }

    // ── Penalty Types ──────────────────────────────────────────────────────

    public function typesIndex(Request $request)
    {
        $q = TalentoPenaltyType::query();
        if ($request->filled('category'))    $q->where('category', $request->category);
        if ($request->boolean('active_only')) $q->active();
        return response()->json($q->orderBy('category')->orderBy('name')->get());
    }

    public function storeType(Request $request)
    {
        $this->authorize('talento.penalties.manage');
        $data = $request->validate([
            'name'         => 'required|string|max:160',
            'category'     => 'required|in:safety,aesthetic,malpractice,other',
            'penalty_kind' => 'required|in:event,status',
            'amount'       => 'required|numeric|min:0',
            'active'       => 'boolean',
        ]);
        return response()->json(TalentoPenaltyType::create($data), 201);
    }

    public function updateType(Request $request, int $id)
    {
        $this->authorize('talento.penalties.manage');
        $type = TalentoPenaltyType::findOrFail($id);
        $data = $request->validate([
            'name'         => 'sometimes|string|max:160',
            'category'     => 'sometimes|in:safety,aesthetic,malpractice,other',
            'penalty_kind' => 'sometimes|in:event,status',
            'amount'       => 'sometimes|numeric|min:0',
            'active'       => 'boolean',
        ]);
        $type->update($data);
        return response()->json($type);
    }

    public function uploadTypeImage(Request $request, int $id)
    {
        $this->authorize('talento.penalties.manage');
        $type = TalentoPenaltyType::findOrFail($id);
        $request->validate(['image' => 'required|image|max:4096']);
        $path = $request->file('image')->store('talento/penalty-types', 'public');
        $type->update(['reference_image_path' => $path]);
        return response()->json(['path' => $path, 'url' => Storage::disk('public')->url($path)]);
    }

    // ── Penalties ──────────────────────────────────────────────────────────

    public function penaltiesIndex(Request $request)
    {
        $q = TalentoPenalty::with(['colaborador.user', 'penaltyType', 'appeal'])
            ->orderByDesc('created_at');

        if ($request->filled('colaborador_id')) $q->where('colaborador_id', $request->colaborador_id);
        if ($request->filled('status'))          $q->where('status', $request->status);
        if ($request->filled('category'))
            $q->whereHas('penaltyType', fn($qq) => $qq->where('category', $request->category));

        return response()->json($q->paginate(25));
    }

    public function showPenalty(int $id)
    {
        return response()->json(
            TalentoPenalty::with(['colaborador.user', 'penaltyType', 'appliedByColaborador.user', 'appeal'])->findOrFail($id)
        );
    }

    /**
     * Apply a new penalty.
     * Photo uses same antifraud as Fase 4a: stored in local disk, not public.
     */
    public function applyPenalty(Request $request)
    {
        $this->authorize('talento.penalties.manage');
        $data = $request->validate([
            'colaborador_id'  => 'required|integer|exists:talento_colaboradores,id',
            'penalty_type_id' => 'required|integer|exists:talento_penalty_types,id',
            'amount'          => 'nullable|numeric|min:0',
            'captured_lat'    => 'nullable|numeric|between:-90,90',
            'captured_lng'    => 'nullable|numeric|between:-180,180',
            'captured_in_app' => 'boolean',
            'notes'           => 'nullable|string|max:1000',
        ]);

        $applierColaborador = TalentoColaborador::where('user_id', auth()->id())->first();
        if (!$applierColaborador) {
            return response()->json(['error' => 'No tienes perfil de colaborador.'], 422);
        }

        $photoPath = null;
        if ($request->hasFile('evidence_photo')) {
            $request->validate(['evidence_photo' => 'image|max:8192']);
            $photoPath = $request->file('evidence_photo')->store(
                'talento/penalties/' . now()->format('Y/m'), 'local'
            );
        }

        $penalty = $this->svc->apply(array_merge($data, [
            'applied_by'          => $applierColaborador->id,
            'evidence_photo_path' => $photoPath,
            'created_by'          => auth()->id(),
        ]));

        return response()->json($penalty->load(['colaborador.user', 'penaltyType']), 201);
    }

    public function serveEvidencePhoto(int $id)
    {
        $this->authorize('talento.penalties.view');
        $penalty = TalentoPenalty::findOrFail($id);
        if (!$penalty->evidence_photo_path || !Storage::disk('local')->exists($penalty->evidence_photo_path)) {
            abort(404);
        }
        return response()->file(Storage::disk('local')->path($penalty->evidence_photo_path));
    }

    // ── Appeals ────────────────────────────────────────────────────────────

    public function appealsIndex(Request $request)
    {
        $q = TalentoPenaltyAppeal::with([
                'penalty.colaborador.user',
                'penalty.penaltyType',
                'penalty.appliedByColaborador.user',
                'appealedByColaborador.user',
                'reviewedByColaborador.user',
            ])
            ->orderByDesc('created_at');

        if ($request->filled('decision'))
            $q->where('decision', $request->decision);
        if ($request->boolean('pending_only'))
            $q->whereNull('decision');

        return response()->json($q->paginate(25));
    }

    public function submitAppeal(Request $request, int $penaltyId)
    {
        $this->authorize('talento.penalties.appeal');
        $penalty = TalentoPenalty::findOrFail($penaltyId);

        if (!in_array($penalty->status, ['applied'])) {
            return response()->json(['error' => 'Solo se puede apelar una penalización en estado "applied".'], 422);
        }
        if ($penalty->appeal()->exists()) {
            return response()->json(['error' => 'Esta penalización ya tiene una apelación abierta.'], 422);
        }

        $colaborador = TalentoColaborador::where('user_id', auth()->id())->first();
        if (!$colaborador) {
            return response()->json(['error' => 'No tienes perfil de colaborador.'], 422);
        }

        $data = $request->validate(['reason' => 'required|string|max:2000']);

        $evidencePath = null;
        if ($request->hasFile('evidence')) {
            $request->validate(['evidence' => 'image|max:8192']);
            $evidencePath = $request->file('evidence')->store(
                'talento/appeals/' . now()->format('Y/m'), 'local'
            );
        }

        $appeal = TalentoPenaltyAppeal::create([
            'penalty_id'   => $penaltyId,
            'appealed_by'  => $colaborador->id,
            'reason'       => $data['reason'],
            'evidence_path'=> $evidencePath,
            'created_at'   => now(),
        ]);
        $penalty->update(['status' => 'appealed']);

        return response()->json($appeal->load(['penalty.penaltyType', 'appealedByColaborador.user']), 201);
    }

    public function resolveAppeal(Request $request, int $appealId)
    {
        $this->authorize('talento.penalties.resolve');
        $appeal = TalentoPenaltyAppeal::with('penalty')->findOrFail($appealId);

        if ($appeal->decision !== null) {
            return response()->json(['error' => 'Esta apelación ya fue resuelta.'], 422);
        }

        $data = $request->validate([
            'decision'       => 'required|in:overturned,upheld',
            'decision_notes' => 'nullable|string|max:2000',
        ]);

        $reviewerColaborador = TalentoColaborador::where('user_id', auth()->id())->first();
        if (!$reviewerColaborador) {
            return response()->json(['error' => 'No tienes perfil de colaborador.'], 422);
        }

        try {
            $resolved = $this->svc->resolveAppeal(
                $appeal,
                $reviewerColaborador->id,
                $data['decision'],
                $data['decision_notes'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($resolved);
    }
}
