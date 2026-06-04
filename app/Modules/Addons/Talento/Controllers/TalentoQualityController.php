<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoCajaBaseline;
use App\Modules\Addons\Talento\Models\TalentoCajaInspection;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoConstructionStandard;
use App\Modules\Addons\Talento\Services\CajaInspectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TalentoQualityController extends Controller
{
    public function __construct(private CajaInspectionService $svc) {}

    // ── Construction Standards ─────────────────────────────────────────────

    public function standardsIndex(Request $request)
    {
        $q = TalentoConstructionStandard::query();
        if ($request->filled('type'))   $q->where('type', $request->type);
        if ($request->boolean('active_only', false)) $q->active();
        return response()->json($q->orderBy('type')->orderBy('name')->get());
    }

    public function storeStandard(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:160',
            'type'        => 'required|in:fusion_loss,power,raqueta,organization,other',
            'ideal_value' => 'nullable|string|max:80',
            'active'      => 'boolean',
        ]);
        $std = TalentoConstructionStandard::create($data);
        return response()->json($std, 201);
    }

    public function updateStandard(Request $request, int $id)
    {
        $std = TalentoConstructionStandard::findOrFail($id);
        $data = $request->validate([
            'name'        => 'sometimes|string|max:160',
            'type'        => 'sometimes|in:fusion_loss,power,raqueta,organization,other',
            'ideal_value' => 'nullable|string|max:80',
            'active'      => 'boolean',
        ]);
        $std->update($data);
        return response()->json($std);
    }

    public function uploadStandardImage(Request $request, int $id)
    {
        $std = TalentoConstructionStandard::findOrFail($id);
        $request->validate(['image' => 'required|image|max:4096']);

        $path = $request->file('image')->store(
            'talento/standards', 'public'
        );
        $std->update(['reference_image_path' => $path]);
        return response()->json(['path' => $path, 'url' => Storage::disk('public')->url($path)]);
    }

    public function destroyStandard(int $id)
    {
        TalentoConstructionStandard::findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    // ── Inspections ────────────────────────────────────────────────────────

    public function inspectionsIndex(Request $request)
    {
        $q = TalentoCajaInspection::with(['colaborador.user', 'project:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('caja_ref'))  $q->where('caja_ref', $request->caja_ref);
        if ($request->filled('project_id')) $q->where('project_id', $request->project_id);
        if ($request->filled('result'))    $q->where('overall_result', $request->result);

        return response()->json($q->paginate(25));
    }

    public function showInspection(int $id)
    {
        $insp = TalentoCajaInspection::with(['colaborador.user', 'project:id,name'])->findOrFail($id);
        return response()->json($insp);
    }

    /**
     * Create a new inspection.
     * Photo upload uses same antifraud as Fase 4a: in-app GPS, no EXIF, watermark stripped.
     * If power_measured is provided, writes/updates talento_caja_baselines (sub-paso 3).
     */
    public function storeInspection(Request $request)
    {
        $data = $request->validate([
            'caja_ref'             => 'required|string|max:60',
            'project_id'           => 'nullable|integer|exists:talento_projects,id',
            'captured_lat'         => 'nullable|numeric|between:-90,90',
            'captured_lng'         => 'nullable|numeric|between:-180,180',
            'captured_in_app'      => 'boolean',
            'fusion_loss_measured' => 'nullable|numeric|min:0|max:99',
            'power_measured'       => 'nullable|numeric|between:-99,0',
            'aesthetic_score'      => 'nullable|integer|between:1,10',
            'notes'                => 'nullable|string|max:1000',
        ]);

        $colaborador = TalentoColaborador::where('user_id', auth()->id())->first();
        if (!$colaborador) {
            return response()->json(['error' => 'No tienes un perfil de colaborador activo.'], 422);
        }

        // Photo upload (optional; antifraud: store in local disk, no public URL)
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $request->validate(['photo' => 'image|max:8192']);
            $photoPath = $request->file('photo')->store(
                'talento/inspections/' . now()->format('Y/m'),
                'local'
            );
        }

        $fusionLoss     = isset($data['fusion_loss_measured']) ? (float) $data['fusion_loss_measured'] : null;
        $power          = isset($data['power_measured'])       ? (float) $data['power_measured']       : null;
        $aestheticScore = $data['aesthetic_score'] ?? null;
        $overallResult  = $this->svc->computeResult($fusionLoss, $power, $aestheticScore);

        $inspection = TalentoCajaInspection::create([
            'caja_ref'             => $data['caja_ref'],
            'project_id'           => $data['project_id'] ?? null,
            'inspected_by'         => $colaborador->id,
            'photo_path'           => $photoPath,
            'captured_lat'         => $data['captured_lat'] ?? null,
            'captured_lng'         => $data['captured_lng'] ?? null,
            'captured_in_app'      => $data['captured_in_app'] ?? false,
            'fusion_loss_measured' => $fusionLoss,
            'power_measured'       => $power,
            'aesthetic_score'      => $aestheticScore,
            'overall_result'       => $overallResult,
            'notes'                => $data['notes'] ?? null,
            'created_by'           => auth()->id(),
            'created_at'           => now(),
        ]);

        // Sub-paso 3: register/update baseline if power was measured
        if ($power !== null) {
            $this->registerBaseline($data['caja_ref'], $power, $colaborador->id, $inspection->id);
        }

        return response()->json($inspection->load(['colaborador.user']), 201);
    }

    /**
     * Trigger IA photo analysis for an existing inspection.
     */
    public function runIaAnalysis(int $id)
    {
        $inspection = TalentoCajaInspection::findOrFail($id);
        $result     = $this->svc->analyzePhoto($inspection);

        $update = ['ia_flags' => $result['flags']];
        if (isset($result['aesthetic_score']) && $result['aesthetic_score'] !== null) {
            $update['aesthetic_score'] = $result['aesthetic_score'];
            // recompute result with IA score
            $update['overall_result'] = $this->svc->computeResult(
                $inspection->fusion_loss_measured,
                $inspection->power_measured,
                $result['aesthetic_score']
            );
        }
        $inspection->update($update);

        return response()->json([
            'ia_flags'      => $result['flags'],
            'summary'       => $result['summary'],
            'overall_result'=> $inspection->fresh()->overall_result,
        ]);
    }

    /**
     * Supervisor validates the inspection (overrides IA suggestion).
     */
    public function supervisorValidate(Request $request, int $id)
    {
        $inspection = TalentoCajaInspection::findOrFail($id);
        $data = $request->validate([
            'overall_result'      => 'required|in:pass,fail,needs_rework',
            'supervisor_validated'=> 'boolean',
            'notes'               => 'nullable|string|max:1000',
        ]);
        $inspection->update(array_merge($data, ['supervisor_validated' => true]));
        return response()->json($inspection);
    }

    // ── Photo serve (antifraud: not public) ───────────────────────────────

    public function servePhoto(int $id)
    {
        $insp = TalentoCajaInspection::findOrFail($id);
        $this->authorize('talento.quality.view');

        if (!$insp->photo_path || !Storage::disk('local')->exists($insp->photo_path)) {
            abort(404);
        }
        return response()->file(Storage::disk('local')->path($insp->photo_path));
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function registerBaseline(string $cajaRef, float $power, int $colaboradorId, int $inspectionId): void
    {
        TalentoCajaBaseline::create([
            'caja_ref'         => $cajaRef,
            'olt_onu_id'       => null,
            'baseline_power_dbm'=> $power,
            'registered_by'    => $colaboradorId,
            'registered_at'    => now(),
            'notes'            => "Auto-registrado desde inspección #{$inspectionId}",
        ]);
    }
}
