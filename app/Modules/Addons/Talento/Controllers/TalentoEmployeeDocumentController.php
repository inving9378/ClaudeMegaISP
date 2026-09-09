<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoEmployeeDocument;
use App\Modules\Addons\Talento\Models\TalentoEmployeeDocumentSignature;
use App\Modules\Addons\Talento\Services\EmployeeDocumentPackageService;
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
 * La firma y "Completar documento" (item #9990661, re-scope gap-driven: rutea a colaborador/
 * user/CompanyInformation, no solo a datos_extra) usan 'talento.expediente.documentos.gestionar'
 * — permiso creado en el item #9990358 previendo justo esta acción ("Subir firmado").
 */
class TalentoEmployeeDocumentController extends Controller
{
    private const FIRMA_DISK_PREFIX = 'private/talento/firmas/';
    private const FIRMA_MAX_BYTES = 2 * 1024 * 1024; // 2MB
    private const FIRMA_MIME_EXT = ['image/png' => 'png', 'image/jpeg' => 'jpeg', 'image/webp' => 'webp'];

    public function forColaborador($colaboradorId)
    {
        $this->authorize('talento.expediente.view');

        $service = app(EmployeeDocumentPackageService::class);

        $documentos = TalentoEmployeeDocument::where('colaborador_id', $colaboradorId)
            ->with(['template:id,name,requires_signature,fillable_fields', 'template.signatureSlots'])
            ->orderBy('id')
            ->get(['id', 'colaborador_id', 'template_id', 'status', 'generated_at', 'signed_at', 'signature_method', 'datos_extra', 'rendered_html']);

        // Item #9990649: una sola query para las firmas por slot de TODOS los documentos
        // listados (evita N+1 dentro del map de abajo).
        $firmasPorDoc = TalentoEmployeeDocumentSignature::whereIn('employee_document_id', $documentos->pluck('id'))
            ->get()
            ->groupBy('employee_document_id');

        $documentos = $documentos->map(function (TalentoEmployeeDocument $doc) use ($colaboradorId, $service, $firmasPorDoc) {
            [$statusFirma, $signatureSlots] = $this->resolveEstadoFirma($doc, $colaboradorId, $firmasPorDoc->get($doc->id) ?? collect());

            // Item #9990661: huecos REALES (parseados del rendered_html), ya no solo el
            // catálogo fijo fillable_fields — ver EmployeeDocumentPackageService::missingFields.
            $huecos = $service->missingFields($doc);

            return [
                'id' => $doc->id,
                'colaborador_id' => $doc->colaborador_id,
                'template_id' => $doc->template_id,
                'template' => $doc->template,
                'status' => $doc->status,
                // status_efectivo: nunca "completo" si falta alguna firma requerida (legado:
                // 1 sola firma del doc; slots: TODOS los slots requerido=true firmados).
                'status_efectivo' => $statusFirma['pendiente_firma'] ? 'pendiente' : $doc->status,
                'generated_at' => $doc->generated_at,
                'requires_signature' => $statusFirma['requiere_firma'],
                'firmado' => $statusFirma['firmado'],
                'pendiente_firma' => $statusFirma['pendiente_firma'],
                'signed_at' => $statusFirma['signed_at'],
                'signature_method' => $doc->signature_method,
                'signature_url' => $statusFirma['signature_url'],
                // Item #9990649: slots declarados por la plantilla (empresa/trabajador/…) con su
                // estado de firma individual — [] si la plantilla no declara slots (legado, 1 sola
                // firma por doc, ver 'firmado'/'signature_url' arriba).
                'signature_slots' => $signatureSlots,
                // Item #9990647/#9990651: catálogo de campos doc.* de ESTE template (group=doc,
                // los únicos que "Completar documento" captura hoy) + los valores ya guardados.
                'fillable_fields' => collect($doc->template->fillable_fields ?? [])->where('group', 'doc')->values(),
                'datos_extra' => $doc->datos_extra ?? (object) [],
                // Item #9990661: gap-driven — dirigido por lo que REALMENTE falta en el
                // render (empleado.*/empresa.*/doc.*/fecha.*/vehiculo.*/herramientas.*), no
                // solo el catálogo doc.* declarado en el template.
                'huecos' => $huecos,
                'huecos_count' => count($huecos),
            ];
        });

        return response()->json($documentos);
    }

    /**
     * Item #9990649: punto único de la regla "¿este documento está firmado/pendiente?", usado
     * por forColaborador(). Slots declarados (talento_document_template_signature_slots no
     * vacío) -> multi-firma: pendiente si CUALQUIER slot requerido=true sigue sin firmar. Sin
     * slots (legado) -> se comporta EXACTAMENTE igual que antes de este item (columna
     * signed_at del propio documento). El item #9990655 (hermano, status_efectivo del padre
     * #9990650) reusa/extrae esta misma regla hacia EmployeeDocumentPackageService — no
     * duplicarla ahí, consumir esta.
     *
     * @return array{0: array{requiere_firma:bool, firmado:bool, pendiente_firma:bool, signed_at:?string, signature_url:?string}, 1: array}
     */
    private function resolveEstadoFirma(TalentoEmployeeDocument $doc, $colaboradorId, $firmasDocColeccion): array
    {
        $slots = $doc->template->signatureSlots ?? collect();

        if ($slots->isEmpty()) {
            $firmado = $doc->signed_at !== null;
            $requiereFirma = (bool) ($doc->template->requires_signature ?? false);

            return [[
                'requiere_firma' => $requiereFirma,
                'firmado' => $firmado,
                'pendiente_firma' => $requiereFirma && !$firmado,
                'signed_at' => $doc->signed_at,
                'signature_url' => $firmado
                    ? "/talento/api/colaboradores/{$colaboradorId}/documentos/{$doc->id}/firma"
                    : null,
            ], []];
        }

        $firmasPorSlot = $firmasDocColeccion->keyBy('slot_key');

        $signatureSlots = $slots->map(function ($slot) use ($firmasPorSlot, $colaboradorId, $doc) {
            $firma = $firmasPorSlot->get($slot->key);
            $firmado = $firma?->signed_at !== null;

            return [
                'key' => $slot->key,
                'label' => $slot->label,
                'firmante_tipo' => $slot->firmante_tipo,
                'orden' => $slot->orden,
                'requerido' => $slot->requerido,
                'firmado' => $firmado,
                'signed_at' => $firma?->signed_at,
                'signature_url' => $firmado
                    ? "/talento/api/colaboradores/{$colaboradorId}/documentos/{$doc->id}/firma?slot_key={$slot->key}"
                    : null,
            ];
        })->values();

        $pendienteFirma = $slots->where('requerido', true)
            ->contains(fn ($slot) => !($firmasPorSlot->get($slot->key)?->signed_at));

        return [[
            'requiere_firma' => true,
            'firmado' => !$pendienteFirma,
            'pendiente_firma' => $pendienteFirma,
            'signed_at' => $firmasPorSlot->max('signed_at'),
            // Legado: sin dueño único de "la" firma cuando hay varios slots — cada recuadro
            // del modal usa su propio signature_url (dentro de signature_slots).
            'signature_url' => null,
        ], $signatureSlots->all()];
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
     *
     * Item #9990649: si la plantilla del documento declara slots (talento_document_template_
     * signature_slots), exige 'slot_key' y guarda en talento_employee_document_signatures (una
     * fila por documento×slot). Si NO declara slots (legado), se comporta EXACTAMENTE igual que
     * antes — mismo endpoint, sin duplicar ruta ni lógica de decodificación de la imagen.
     */
    public function sign(Request $request, $colaboradorId, $docId)
    {
        $this->authorize('talento.expediente.documentos.gestionar');

        $documento = TalentoEmployeeDocument::where('colaborador_id', $colaboradorId)
            ->where('id', $docId)
            ->with('template.signatureSlots')
            ->firstOrFail();

        $slots = $documento->template->signatureSlots ?? collect();
        $slotKey = null;

        if ($slots->isNotEmpty()) {
            $request->validate(['slot_key' => 'required|string']);
            $slotKey = $request->input('slot_key');
            abort_unless($slots->contains('key', $slotKey), 422, 'El slot de firma indicado no existe en esta plantilla.');
        }

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

        $path = self::FIRMA_DISK_PREFIX . "{$colaboradorId}/{$docId}_" . ($slotKey ? "{$slotKey}_" : '') . time() . '.' . $extension;
        Storage::disk('local')->put($path, $binario);

        if ($slotKey) {
            $firma = TalentoEmployeeDocumentSignature::firstOrNew([
                'employee_document_id' => $documento->id,
                'slot_key' => $slotKey,
            ]);
            $anterior = $firma->signature_path;
            $firma->fill([
                'signature_path' => $path,
                'signed_at' => now(),
                'signed_by' => auth()->id(),
                'signature_method' => $metodo,
            ])->save();

            if ($anterior && $anterior !== $path && Storage::disk('local')->exists($anterior)) {
                Storage::disk('local')->delete($anterior);
            }

            return response()->json([
                'id' => $documento->id,
                'slot_key' => $slotKey,
                'signed_at' => $firma->signed_at,
                'signature_method' => $firma->signature_method,
                'signature_url' => "/talento/api/colaboradores/{$colaboradorId}/documentos/{$docId}/firma?slot_key={$slotKey}",
            ]);
        }

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
     * Item #9990661 — huecos REALES de un documento (parseados del rendered_html guardado, no
     * el catálogo fijo doc.*): {ruta, label, destino, tipo, editable} por cada campo que el
     * render dejó marcado .campo-faltante. Mismo scope anti-IDOR que show()/sign().
     */
    public function huecos($colaboradorId, $docId)
    {
        $this->authorize('talento.expediente.view');

        $documento = TalentoEmployeeDocument::where('colaborador_id', $colaboradorId)
            ->where('id', $docId)
            ->with('template:id,name,requires_signature,fillable_fields')
            ->firstOrFail();

        $huecos = app(EmployeeDocumentPackageService::class)->missingFields($documento);

        return response()->json([
            'id' => $documento->id,
            'huecos' => $huecos,
            'huecos_count' => count($huecos),
        ]);
    }

    /**
     * Item #9990661 — re-scope gap-driven de #9990647/#9990651: ya no solo escribe doc.* a
     * datos_extra, rutea CADA campo a su lugar real (colaborador/user si es dato del empleado,
     * CompanyInformation si es dato de la empresa —comparte con TODOS los colaboradores—, o
     * datos_extra si es puramente del documento). El mapa inverso whitelisteado vive en
     * EmployeeDocumentPackageService — aquí solo se pasa el array crudo {ruta:valor}. Gate
     * elevado a 'talento.expediente.documentos.gestionar' (mismo que sign(): esto ya escribe
     * sobre el expediente real, no solo sobre el documento). Mismo scope anti-IDOR que
     * show()/sign() (colaborador_id+docId, nunca el id crudo del documento).
     */
    public function completar(Request $request, $colaboradorId, $docId)
    {
        $this->authorize('talento.expediente.documentos.gestionar');

        $documento = TalentoEmployeeDocument::where('colaborador_id', $colaboradorId)
            ->where('id', $docId)
            ->firstOrFail();

        $campos = $request->input('campos', []);
        abort_unless(is_array($campos), 422, 'campos debe ser un objeto/arreglo.');

        $resultado = app(EmployeeDocumentPackageService::class)->completar($documento, $campos);
        $documento = $resultado['documento'];

        return response()->json([
            'id' => $documento->id,
            'status' => $documento->status,
            'generated_at' => $documento->generated_at,
            'afecta_a_todos' => $resultado['afecta_a_todos'],
            'huecos' => $resultado['huecos'],
            'huecos_count' => count($resultado['huecos']),
        ]);
    }

    /**
     * Sirve la imagen de la firma. Privada, acotada a colaborador_id+docId (anti-IDOR) — mismo
     * patrón que WhatsappReceiptReviewController::media(). Item #9990649: con querystring
     * ?slot_key=... sirve la firma de ESE slot (talento_employee_document_signatures); sin él,
     * se comporta igual que antes (columna legado del propio documento).
     */
    public function firma(Request $request, $colaboradorId, $docId)
    {
        $this->authorize('talento.expediente.view');

        $slotKey = $request->query('slot_key');

        if ($slotKey) {
            $documento = TalentoEmployeeDocument::where('colaborador_id', $colaboradorId)
                ->where('id', $docId)
                ->firstOrFail();

            $firma = TalentoEmployeeDocumentSignature::where('employee_document_id', $documento->id)
                ->where('slot_key', $slotKey)
                ->whereNotNull('signature_path')
                ->firstOrFail();

            abort_unless(Storage::disk('local')->exists($firma->signature_path), 404);

            return Storage::disk('local')->response($firma->signature_path);
        }

        $documento = TalentoEmployeeDocument::where('colaborador_id', $colaboradorId)
            ->where('id', $docId)
            ->whereNotNull('signature_path')
            ->firstOrFail();

        abort_unless(Storage::disk('local')->exists($documento->signature_path), 404);

        return Storage::disk('local')->response($documento->signature_path);
    }
}
