<?php

namespace App\Modules\Addons\MonitoreoOficial\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MonitoreoOficial\Models\MonitoringCase;
use App\Modules\Addons\MonitoreoOficial\Models\MonitoringCaseDocument;
use App\Modules\Addons\MonitoreoOficial\Models\MonitoringModuleConnection;
use App\Modules\Addons\MonitoreoOficial\Models\MonitoringSource;
use App\Modules\Addons\MonitoreoOficial\Services\CapabilityDetector;
use App\Modules\Addons\MonitoreoOficial\Services\CaseDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MonitoringController extends Controller
{
    public function dashboard()
    {
        abort_unless(auth()->user()?->can('monitoring.view'), 403);

        // Mantener el panel de conexiones al día (best-effort, idempotente).
        try {
            app(CapabilityDetector::class)->refreshConnections();
        } catch (\Throwable $e) {
            // Nunca romper el dashboard por la detección.
        }

        $connections = MonitoringModuleConnection::where('consumer_module', 'addon-monitoreo-oficial')
            ->orderBy('provider_module')->get();

        $cases = MonitoringCase::with('source')->orderByDesc('id')->get();

        return view('addon-monitoreo-oficial::dashboard', compact('connections', 'cases'));
    }

    public function configuracion()
    {
        abort_unless(auth()->user()?->can('monitoring.manage_integrations'), 403);

        $connections = MonitoringModuleConnection::where('consumer_module', 'addon-monitoreo-oficial')
            ->orderBy('provider_module')->get();

        $sources = MonitoringSource::orderBy('name')->get();

        return view('addon-monitoreo-oficial::configuracion', compact('connections', 'sources'));
    }

    // ── Expedientes (monitoring_cases) — Fase 2 ────────────────────────────

    public function crearExpediente()
    {
        abort_unless(auth()->user()?->can('monitoring.manage_cases'), 403);

        $sources = MonitoringSource::orderBy('name')->get();
        $case = new MonitoringCase(['monitoring_status' => 'activo']);

        return view('addon-monitoreo-oficial::expediente_form', compact('sources', 'case'));
    }

    public function guardarExpediente(Request $request)
    {
        abort_unless(auth()->user()?->can('monitoring.manage_cases'), 403);

        $data = $this->validarExpediente($request);
        $data['is_demo'] = false;
        $data['created_by'] = auth()->id();

        MonitoringCase::create($data);

        return redirect()->route('monitoreo-oficial.dashboard')->with('success', 'Expediente registrado correctamente.');
    }

    public function editarExpediente(int $id)
    {
        abort_unless(auth()->user()?->can('monitoring.manage_cases'), 403);

        $case = MonitoringCase::findOrFail($id);
        $sources = MonitoringSource::orderBy('name')->get();

        return view('addon-monitoreo-oficial::expediente_form', compact('sources', 'case'));
    }

    public function actualizarExpediente(Request $request, int $id)
    {
        abort_unless(auth()->user()?->can('monitoring.manage_cases'), 403);

        $case = MonitoringCase::findOrFail($id);
        $data = $this->validarExpediente($request, $case->id);
        $case->update($data);

        return redirect()->route('monitoreo-oficial.dashboard')->with('success', 'Expediente actualizado correctamente.');
    }

    public function eliminarExpediente(int $id)
    {
        abort_unless(auth()->user()?->can('monitoring.manage_cases'), 403);

        $case = MonitoringCase::findOrFail($id);
        // El expediente de ejemplo de MEGANET NO se borra desde la UI — solo lo retira la
        // desinstalación del módulo (o a mano, por tinker, con una decisión explícita).
        abort_if($case->is_demo, 403, 'El expediente de ejemplo no se puede eliminar desde aquí.');
        $case->delete();

        return redirect()->route('monitoreo-oficial.dashboard')->with('success', 'Expediente eliminado.');
    }

    private function validarExpediente(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'source_id' => ['nullable', 'exists:monitoring_sources,id'],
            'name' => ['required', 'string', 'max:255'],
            'authority' => ['required', 'string', 'max:255'],
            'case_number' => [
                'nullable', 'string', 'max:255',
                Rule::unique('monitoring_cases', 'case_number')
                    ->where(fn ($q) => $q->where('authority', $request->input('authority')))
                    ->ignore($ignoreId),
            ],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'class_code' => ['nullable', 'string', 'max:255'],
            'current_legal_status' => ['nullable', 'string', 'max:1000'],
            'monitoring_status' => ['required', Rule::in(['activo', 'pausado'])],
        ]);
    }

    // ── Ficha del expediente + documentos (monitoring_case_documents) — Fase 3 ─

    public function verExpediente(int $id)
    {
        abort_unless(auth()->user()?->can('monitoring.view'), 403);

        $case = MonitoringCase::with(['source', 'documents.uploader'])->findOrFail($id);

        return view('addon-monitoreo-oficial::expediente_show', compact('case'));
    }

    public function subirDocumento(Request $request, int $id, CaseDocumentService $service)
    {
        abort_unless(auth()->user()?->can('monitoring.manage_cases'), 403);

        $case = MonitoringCase::findOrFail($id);

        $data = $request->validate([
            'documento' => ['required', 'file', 'mimes:pdf', 'max:15360'], // 15 MB
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $resultado = $service->subir($case, $data['documento'], auth()->id(), $data['notes'] ?? null);

        $mensaje = $resultado['es_identico_al_anterior']
            ? 'Documento subido. Es idéntico al último que ya tenías — no se marcó como cambio.'
            : 'Documento subido y registrado como nueva versión del expediente.';

        return redirect()->route('monitoreo-oficial.expedientes.ver', $case->id)->with('success', $mensaje);
    }

    public function descargarDocumento(int $documentId)
    {
        abort_unless(auth()->user()?->can('monitoring.view'), 403);

        $document = MonitoringCaseDocument::findOrFail($documentId);
        abort_unless(Storage::disk('local')->exists($document->stored_path), 404);

        return Storage::disk('local')->download($document->stored_path, $document->original_filename);
    }

    public function eliminarDocumento(int $documentId)
    {
        abort_unless(auth()->user()?->can('monitoring.manage_cases'), 403);

        $document = MonitoringCaseDocument::findOrFail($documentId);
        $caseId = $document->case_id;

        Storage::disk('local')->delete($document->stored_path);
        $document->delete();

        return redirect()->route('monitoreo-oficial.expedientes.ver', $caseId)->with('success', 'Documento eliminado.');
    }

    // ── Fuentes oficiales (monitoring_sources) — Fase 2 ────────────────────

    public function crearFuente()
    {
        abort_unless(auth()->user()?->can('monitoring.manage_sources'), 403);

        $source = new MonitoringSource(['status' => 'disponible']);

        return view('addon-monitoreo-oficial::fuente_form', compact('source'));
    }

    public function guardarFuente(Request $request)
    {
        abort_unless(auth()->user()?->can('monitoring.manage_sources'), 403);

        $data = $this->validarFuente($request);
        MonitoringSource::create($data);

        return redirect()->route('monitoreo-oficial.configuracion')->with('success', 'Fuente registrada correctamente.');
    }

    public function editarFuente(int $id)
    {
        abort_unless(auth()->user()?->can('monitoring.manage_sources'), 403);

        $source = MonitoringSource::findOrFail($id);

        return view('addon-monitoreo-oficial::fuente_form', compact('source'));
    }

    public function actualizarFuente(Request $request, int $id)
    {
        abort_unless(auth()->user()?->can('monitoring.manage_sources'), 403);

        $source = MonitoringSource::findOrFail($id);
        $data = $this->validarFuente($request);
        $source->update($data);

        return redirect()->route('monitoreo-oficial.configuracion')->with('success', 'Fuente actualizada correctamente.');
    }

    public function eliminarFuente(int $id)
    {
        abort_unless(auth()->user()?->can('monitoring.manage_sources'), 403);

        $source = MonitoringSource::findOrFail($id);
        // Nunca tronar por un expediente que ya referencia esta fuente: se desvincula
        // (source_id nullOnDelete, ya lo hace la FK), no se bloquea el borrado.
        $source->delete();

        return redirect()->route('monitoreo-oficial.configuracion')->with('success', 'Fuente eliminada.');
    }

    private function validarFuente(Request $request): array
    {
        return $request->validate([
            'authority' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'source_type' => ['required', Rule::in(['manual_pdf', 'url', 'siga', 'vidoc', 'gaceta'])],
            'base_url' => ['nullable', 'url', 'max:500'],
            'status' => ['required', Rule::in(['disponible', 'inactiva'])],
        ]);
    }
}
