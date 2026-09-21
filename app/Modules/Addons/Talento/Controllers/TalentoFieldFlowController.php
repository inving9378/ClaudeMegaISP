<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoInstallationSurvey;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderMedia;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderSignature;
use App\Modules\Addons\Talento\Services\FieldFlowService;
use App\Modules\Addons\Talento\Services\FieldIaValidationService;
use App\Modules\Addons\Talento\Services\FieldMediaService;
use App\Modules\Addons\Talento\Services\OrdenTrabajoUnifiedService;
use App\Modules\Addons\Talento\Services\SignatureService;
use App\Modules\Addons\Talento\Support\FieldFlowEntity;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TalentoFieldFlowController extends Controller
{
    public function __construct(
        private FieldMediaService      $mediaService,
        private FieldIaValidationService $iaService,
        private FieldFlowService       $flowService,
        private OrdenTrabajoUnifiedService $unified
    ) {}

    // ── Vista admin del flujo ─────────────────────────────────────────────────

    public function index()
    {
        $this->authorize('talento.work_orders.view');
        return view('addon-talento::talento.campo');
    }

    // ── Sub-paso 1: Evidencia fotográfica ─────────────────────────────────────

    public function uploadMedia(Request $request, $workOrderId)
    {
        $this->authorize('talento.media.upload');

        $data = $request->validate([
            'file'        => 'required|file|mimes:jpg,jpeg,png|max:10240',
            'type'        => 'required|in:presentation,completion,ine_front,ine_back,proof_address,modem_sn,other',
            'captured_lat'=> 'nullable|numeric|between:-90,90',
            'captured_lng'=> 'nullable|numeric|between:-180,180',
            'captured_at' => 'nullable|date',
        ]);

        $resolved = FieldFlowEntity::resolve((int)$workOrderId);
        abort_if(! $resolved, 404, 'Orden de trabajo no encontrada.');

        $media = $this->mediaService->store(
            $data['file'],
            $resolved['origen'],
            (int)$workOrderId,
            $data['type'],
            isset($data['captured_lat']) ? (float)$data['captured_lat'] : null,
            isset($data['captured_lng']) ? (float)$data['captured_lng'] : null,
            $data['captured_at'] ?? null
        );

        return response()->json([
            'id'               => $media->id,
            'type'             => $media->type,
            'captured_at'      => $media->captured_at,
            'watermark_applied'=> $media->watermark_applied,
            'location_flagged' => $media->location_flagged,
            'distance_m'       => $media->location_distance_m,
        ], 201);
    }

    public function listMedia($workOrderId)
    {
        $this->authorize('talento.media.view');

        $canViewSensitive = auth()->user()?->can('talento.media.view_sensitive');

        $media = TalentoWorkOrderMedia::where(FieldFlowEntity::fkColumn($workOrderId), $workOrderId)
            ->orderBy('created_at')
            ->get()
            ->map(function ($m) use ($canViewSensitive) {
                $row = [
                    'id'               => $m->id,
                    'type'             => $m->type,
                    'captured_at'      => $m->captured_at,
                    'captured_lat'     => $m->captured_lat,
                    'captured_lng'     => $m->captured_lng,
                    'watermark_applied'=> $m->watermark_applied,
                    'location_flagged' => $m->location_flagged,
                    'distance_m'       => $m->location_distance_m,
                ];
                // Only expose URL for sensitive docs if user has the sensitive permission
                if (!$m->isSensitive() || $canViewSensitive) {
                    $row['url'] = route('talento.media.serve', ['id' => $m->id]);
                } else {
                    $row['url'] = null;
                    $row['restricted'] = true;
                }
                return $row;
            });

        return response()->json($media);
    }

    public function serveMedia($id)
    {
        $media = TalentoWorkOrderMedia::findOrFail($id);

        if ($media->isSensitive()) {
            $this->authorize('talento.media.view_sensitive');
        } else {
            $this->authorize('talento.media.view');
        }

        $path = $this->mediaService->resolvePath($media);

        if (!Storage::disk('local')->exists($path)) {
            abort(404, 'Archivo no encontrado.');
        }

        return Storage::disk('local')->response($path);
    }

    // ── Sub-paso 2: Validación IA ─────────────────────────────────────────────

    public function runIaValidation($workOrderId)
    {
        $this->authorize('talento.ia_validation.view');

        $result = $this->iaService->validateOrder((int)$workOrderId);

        return response()->json($result);
    }

    public function overrideIaValidation(Request $request, $validationId)
    {
        $this->authorize('talento.ia_validation.override');

        $data = $request->validate(['reason' => 'required|string|max:255']);

        $result = $this->iaService->override((int)$validationId, $data['reason']);

        return response()->json($result);
    }

    public function getIaValidation($workOrderId)
    {
        $this->authorize('talento.ia_validation.view');

        $validation = \App\Modules\Addons\Talento\Models\TalentoWorkOrderIaValidation
            ::where(FieldFlowEntity::fkColumn($workOrderId), $workOrderId)
            ->where('validation_type', 'field_flow')
            ->latest('created_at')
            ->first();

        return response()->json($validation);
    }

    // ── Sub-paso 3: Firmas ────────────────────────────────────────────────────

    public function storeSignature(Request $request, $workOrderId)
    {
        $this->authorize('talento.field_flow.accept');

        $data = $request->validate([
            'signer_type'    => 'required|in:technician,client',
            'signature_data' => 'required|string',   // base64 SVG/PNG from app canvas
            'signed_lat'     => 'nullable|numeric|between:-90,90',
            'signed_lng'     => 'nullable|numeric|between:-180,180',
            'signed_at'      => 'nullable|date',
        ]);

        // Resuelve la OT como work_order o como task (la operación de campo corre en tasks).
        $resolved = FieldFlowEntity::resolve((int) $workOrderId);
        abort_if(! $resolved, 404);

        $sig = app(SignatureService::class)->store(
            $resolved['origen'],
            (int) $workOrderId,
            $data['signer_type'],
            $data['signature_data'],
            isset($data['signed_lat']) ? (float) $data['signed_lat'] : null,
            isset($data['signed_lng']) ? (float) $data['signed_lng'] : null,
            $data['signed_at'] ?? null,
            (int) auth()->id()
        );

        return response()->json($sig, 201);
    }

    public function getSignatures($workOrderId)
    {
        $this->authorize('talento.signatures.view');

        $sigs = TalentoWorkOrderSignature::where(FieldFlowEntity::fkColumn($workOrderId), $workOrderId)->get(['id','signer_type','signed_at','signed_lat','signed_lng']);
        return response()->json($sigs);
    }

    // ── Sub-paso 4: Aceptar ───────────────────────────────────────────────────

    public function accept(Request $request, $workOrderId)
    {
        $this->authorize('talento.field_flow.accept');

        $data = $request->validate([
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        try {
            $order = $this->flowService->accept(
                (int)$workOrderId,
                isset($data['latitude'])  ? (float)$data['latitude']  : null,
                isset($data['longitude']) ? (float)$data['longitude'] : null
            );
            return response()->json($order);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // ── Sub-paso 5: Activación ────────────────────────────────────────────────

    public function confirmActivation(Request $request, $workOrderId)
    {
        $this->authorize('talento.activations.manage');

        $data = $request->validate(['dispatch_olt' => 'boolean']);

        try {
            $order = $this->flowService->confirmActivation(
                (int)$workOrderId,
                (bool)($data['dispatch_olt'] ?? false)
            );
            return response()->json($order);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function getActivation($workOrderId)
    {
        $this->authorize('talento.activations.manage');

        // work_order_id y tarea_id son secuencias de ids independientes — nunca
        // hacer OR entre ambas sin resolver primero el origen real, o dos filas
        // de órdenes distintas con el mismo número podrían mezclarse.
        $fkCol = $this->resolveFieldFlowOwnerColumn($workOrderId);
        $activation = \App\Modules\Addons\Talento\Models\TalentoWorkOrderActivation
            ::where($fkCol, $workOrderId)
            ->latest('created_at')
            ->first();

        return response()->json($activation);
    }

    /** 'work_order_id' si $id es una OT real; 'tarea_id' en cualquier otro caso. */
    private function resolveFieldFlowOwnerColumn($id): string
    {
        return FieldFlowEntity::fkColumn((int) $id);
    }

    // ── Sub-paso 6: Onboarding + encuesta ────────────────────────────────────

    public function onboard($workOrderId)
    {
        $this->authorize('talento.activations.manage');

        try {
            $result = $this->flowService->onboard((int)$workOrderId);
            return response()->json($result);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function submitSurvey(Request $request, $workOrderId)
    {
        $this->authorize('talento.survey.manage');

        $data = $request->validate([
            'rating_overall'      => 'nullable|integer|between:1,5',
            'rating_technician'   => 'nullable|integer|between:1,5',
            'comments'            => 'nullable|string|max:1000',
            'google_review_offered'=> 'boolean',
            'google_review_opened' => 'boolean',
        ]);

        $survey = $this->flowService->submitSurvey((int)$workOrderId, $data);
        return response()->json($survey);
    }

    public function getSurvey($workOrderId)
    {
        $this->authorize('talento.survey.view');

        $fkCol = $this->resolveFieldFlowOwnerColumn($workOrderId);
        $survey = TalentoInstallationSurvey::where($fkCol, $workOrderId)->first();
        return response()->json($survey);
    }

    // ── Vista completa del flujo de campo de una orden ────────────────────────

    public function fieldFlowState($workOrderId)
    {
        $this->authorize('talento.work_orders.view');

        // El listado admin (OrdenTrabajoUnifiedService::listForAdmin) unifica
        // talento_work_orders + tasks (tipo=campo) en una sola tabla — "Ver
        // flujo" debe poder abrir CUALQUIERA de las dos filas, no solo las
        // que ya viven en talento_work_orders (showForAdmin ya sabe resolver
        // ambos orígenes y arma el mismo shape que el front espera en `order`).
        $order = $this->unified->showForAdmin((int) $workOrderId);
        abort_if(! $order, 404, 'Orden de trabajo no encontrada.');

        $fkCol = FieldFlowEntity::fkColumn((int) $workOrderId);
        $canViewSensitive = auth()->user()?->can('talento.media.view_sensitive');

        $media = TalentoWorkOrderMedia::where($fkCol, $workOrderId)
            ->orderBy('created_at')
            ->get()
            ->map(fn($m) => [
                'id'   => $m->id,
                'type' => $m->type,
                'url'  => (!$m->isSensitive() || $canViewSensitive)
                    ? route('talento.media.serve', ['id' => $m->id])
                    : null,
                'watermark_applied' => $m->watermark_applied,
                'location_flagged'  => $m->location_flagged,
                'captured_at'       => $m->captured_at,
            ]);

        $signatures = TalentoWorkOrderSignature::where($fkCol, $workOrderId)
            ->get(['id', 'signer_type', 'signed_at']);

        $iaValidation = \App\Modules\Addons\Talento\Models\TalentoWorkOrderIaValidation
            ::where($fkCol, $workOrderId)
            ->where('validation_type', 'field_flow')
            ->latest('created_at')
            ->first();

        $activation = \App\Modules\Addons\Talento\Models\TalentoWorkOrderActivation
            ::where($fkCol, $workOrderId)
            ->latest('created_at')
            ->first();

        $survey = TalentoInstallationSurvey::where($fkCol, $workOrderId)->first();

        // Para una task, activation_confirmed_at/activation_by no existen como
        // columna propia (esa columna solo vive en talento_work_orders) — el
        // front lee flow.order.activation_confirmed_at para decidir si ya se
        // activó, así que se refleja aquí desde la fila real de activación.
        if ($fkCol === 'tarea_id' && $activation?->activated_at) {
            $order['activation_confirmed_at'] = $activation->activated_at;
            $order['activation_by']           = $activation->activated_by;
        }

        return response()->json([
            'order'         => $order,
            'media'         => $media,
            'signatures'    => $signatures,
            'ia_validation' => $iaValidation,
            'activation'    => $activation,
            'survey'        => $survey,
        ]);
    }
}
