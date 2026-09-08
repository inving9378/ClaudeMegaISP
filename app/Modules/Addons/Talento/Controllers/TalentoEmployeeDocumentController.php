<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoEmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Item roadmap #871 (Expediente RH — Hijo D2), fase C. Endpoints sobre los documentos ya
 * generados por EmployeeDocumentPackageService (fases A+B). Sin edición ni regeneración manual
 * desde aquí — regla anti-desincronización del item padre. Sí permite FIRMAR el documento ya
 * generado (item #9990618, fase 1: backend) — no reescribe rendered_html ni el motor de plantillas.
 *
 * Gate 'talento.expediente.view' (NO 'talento.view'): el HTML renderizado trae CURP/NSS/RFC/
 * salario/domicilio (ver EmployeeDocumentPackageService::empleadoData), los mismos campos
 * sensibles que el item #199 aisló detrás de este permiso propio en TalentoColaboradorController.
 * La firma (escritura) usa 'talento.expediente.documentos.gestionar' — permiso creado en el
 * item #9990358 previendo justo esta acción ("Subir firmado").
 */
class TalentoEmployeeDocumentController extends Controller
{
    private const FIRMA_DISK_PREFIX = 'private/talento/firmas/';
    private const FIRMA_MAX_BYTES = 2 * 1024 * 1024; // 2MB
    private const FIRMA_MIME_EXT = ['image/png' => 'png', 'image/jpeg' => 'jpeg', 'image/webp' => 'webp'];

    public function forColaborador($colaboradorId)
    {
        $this->authorize('talento.expediente.view');

        $documentos = TalentoEmployeeDocument::where('colaborador_id', $colaboradorId)
            ->with('template:id,name,requires_signature')
            ->orderBy('id')
            ->get(['id', 'colaborador_id', 'template_id', 'status', 'generated_at', 'signed_at', 'signature_method'])
            ->map(function (TalentoEmployeeDocument $doc) use ($colaboradorId) {
                $requiereFirma = (bool) ($doc->template->requires_signature ?? false);
                $firmado = $doc->signed_at !== null;

                return [
                    'id' => $doc->id,
                    'colaborador_id' => $doc->colaborador_id,
                    'template_id' => $doc->template_id,
                    'template' => $doc->template,
                    'status' => $doc->status,
                    // status_efectivo: nunca "completo" si la plantilla exige firma y aún no la tiene.
                    'status_efectivo' => ($requiereFirma && !$firmado) ? 'pendiente' : $doc->status,
                    'generated_at' => $doc->generated_at,
                    'requires_signature' => $requiereFirma,
                    'firmado' => $firmado,
                    'pendiente_firma' => $requiereFirma && !$firmado,
                    'signed_at' => $doc->signed_at,
                    'signature_method' => $doc->signature_method,
                    'signature_url' => $firmado
                        ? "/talento/api/colaboradores/{$colaboradorId}/documentos/{$doc->id}/firma"
                        : null,
                ];
            });

        return response()->json($documentos);
    }

    public function show($colaboradorId, $docId)
    {
        $this->authorize('talento.expediente.view');

        $documento = TalentoEmployeeDocument::where('colaborador_id', $colaboradorId)
            ->where('id', $docId)
            ->firstOrFail();

        return response($documento->rendered_html, Response::HTTP_OK)
            ->header('Content-Type', 'text/html');
    }

    /**
     * Firma un documento ya generado: (a) PNG/JPEG/WEBP en data URL (pad de canvas) vía el
     * campo 'signature', o (b) archivo de imagen subido vía 'signature_file'. Sobreescribe la
     * firma previa si ya existía (re-firmar). Scope estricto colaborador_id+docId — nunca por
     * id crudo del documento.
     */
    public function sign(Request $request, $colaboradorId, $docId)
    {
        $this->authorize('talento.expediente.documentos.gestionar');

        $documento = TalentoEmployeeDocument::where('colaborador_id', $colaboradorId)
            ->where('id', $docId)
            ->firstOrFail();

        if ($request->hasFile('signature_file')) {
            $request->validate([
                'signature_file' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048',
            ]);

            $file = $request->file('signature_file');
            $binario = file_get_contents($file->getRealPath());
            $extension = $file->getClientOriginalExtension() ?: 'png';
            $extension = $extension === 'jpg' ? 'jpeg' : $extension;
            $metodo = 'uploaded';
        } else {
            $request->validate(['signature' => 'required|string']);

            $data = $request->input('signature');
            $extension = 'png';
            if (str_starts_with($data, 'data:')) {
                if (!preg_match('/^data:(image\/(?:png|jpe?g|webp));base64,/', $data, $m)) {
                    abort(422, 'Formato de firma no soportado (usa PNG, JPEG o WEBP).');
                }
                $extension = self::FIRMA_MIME_EXT[$m[1]] ?? 'png';
                $data = preg_replace('/^data:[^,]+,/', '', $data);
            }

            $binario = base64_decode($data, true);
            if ($binario === false || $binario === '') {
                abort(422, 'No se pudo decodificar la firma.');
            }
            if (@getimagesizefromstring($binario) === false) {
                abort(422, 'La imagen de la firma no es válida.');
            }
            $metodo = 'drawn';
        }

        if (strlen($binario) > self::FIRMA_MAX_BYTES) {
            abort(422, 'La firma excede el tamaño máximo permitido (2MB).');
        }

        $path = self::FIRMA_DISK_PREFIX . "{$colaboradorId}/{$docId}_" . time() . '.' . $extension;
        Storage::disk('local')->put($path, $binario);

        $anterior = $documento->signature_path;
        $documento->update([
            'signature_path' => $path,
            'signed_at' => now(),
            'signed_by' => auth()->id(),
            'signature_method' => $metodo,
        ]);

        if ($anterior && $anterior !== $path && Storage::disk('local')->exists($anterior)) {
            Storage::disk('local')->delete($anterior);
        }

        return response()->json([
            'id' => $documento->id,
            'signed_at' => $documento->signed_at,
            'signature_method' => $documento->signature_method,
            'signature_url' => "/talento/api/colaboradores/{$colaboradorId}/documentos/{$docId}/firma",
        ]);
    }

    /**
     * Sirve la imagen de la firma. Privada, acotada a colaborador_id+docId (anti-IDOR) — mismo
     * patrón que WhatsappReceiptReviewController::media().
     */
    public function firma($colaboradorId, $docId)
    {
        $this->authorize('talento.expediente.view');

        $documento = TalentoEmployeeDocument::where('colaborador_id', $colaboradorId)
            ->where('id', $docId)
            ->whereNotNull('signature_path')
            ->firstOrFail();

        abort_unless(Storage::disk('local')->exists($documento->signature_path), 404);

        return Storage::disk('local')->response($documento->signature_path);
    }
}
