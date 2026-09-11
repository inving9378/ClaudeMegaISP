<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoDocumentTemplate;
use App\Modules\Addons\Talento\Models\TalentoDocumentTemplateVersion;
use App\Modules\Addons\Talento\Models\TalentoEmployeeDocument;
use App\Modules\Addons\Talento\Models\TalentoEmployeeDocumentReapertura;
use App\Modules\Addons\Talento\Models\TalentoEmployeeDocumentSignature;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Item roadmap #9990806 (sub-item de #9990792). Cuando una plantilla tipo='acuse' gana una
 * version "mayor" (ver TemplateVersionService::createVersion), todo talento_employee_documents
 * con status='completo' de esa plantilla vuelve a 'pendiente' -- solo acuse, nunca 'firma' (un
 * contrato firmado no se re-firma solo porque cambio texto legal menor; eso es decision aparte
 * de Irving, no se asume aqui).
 *
 * Antes de tocar nada, snapshotea a talento_employee_document_reaperturas (tabla append-only):
 * ni talento_employee_documents ni talento_employee_document_signatures son historicas -- el
 * propio sign() reusa la misma fila via firstOrNew al re-firmar -- asi que sin este snapshot la
 * evidencia de "firmo la version N" se perderia en cuanto alguien vuelva a firmar (decision q2).
 */
class AcuseReopeningService
{
    public function __construct(private EmployeeDocumentPackageService $packageService)
    {
    }

    /**
     * @return int Cuantos talento_employee_documents se reabrieron.
     */
    public function reopenForNewVersion(TalentoDocumentTemplate $template, TalentoDocumentTemplateVersion $newVersion): int
    {
        if ($template->tipo !== 'acuse') {
            return 0;
        }

        $documentos = TalentoEmployeeDocument::where('template_id', $template->id)
            ->where('status', 'completo')
            ->get();

        if ($documentos->isEmpty()) {
            return 0;
        }

        $slots = $template->signatureSlots()->get()->keyBy('key');

        foreach ($documentos as $documento) {
            DB::transaction(function () use ($documento, $newVersion, $slots) {
                $firmasSlots = TalentoEmployeeDocumentSignature::where('employee_document_id', $documento->id)->get();

                TalentoEmployeeDocumentReapertura::create([
                    'employee_document_id' => $documento->id,
                    'template_version_id_anterior' => $documento->template_version_id,
                    'rendered_html_anterior' => $documento->rendered_html,
                    'status_anterior' => $documento->status,
                    'signed_at_anterior' => $documento->signed_at,
                    'signed_by_anterior' => $documento->signed_by,
                    'signature_method_anterior' => $documento->signature_method,
                    'signature_path_anterior' => $documento->signature_path,
                    'firmas_slots_anterior' => $firmasSlots->isEmpty() ? null : $firmasSlots->map(fn ($f) => [
                        'slot_key' => $f->slot_key,
                        'label' => $slots->get($f->slot_key)?->label,
                        'signed_at' => $f->signed_at,
                        'signed_by' => $f->signed_by,
                        'signature_method' => $f->signature_method,
                        'signature_path' => $f->signature_path,
                    ])->values()->all(),
                    'motivo' => 'nueva_version_mayor',
                    'created_at' => now(),
                ]);

                // Limpia el estado VIGENTE (no el snapshot de arriba, que ya lo conserva) para
                // que SignatureSlotStatus::pendienteFirma() detecte que falta re-firmar. Los
                // archivos de firma NO se borran de disco (quedan huerfanos a proposito: el
                // snapshot referencia su path para auditoria).
                $firmasSlots->each->delete();

                $documento->update([
                    'status' => 'pendiente',
                    'signed_at' => null,
                    'signed_by' => null,
                    'signature_method' => null,
                    'signature_path' => null,
                ]);
            });

            // Fuera de la transaccion: regenerateOne() re-renderiza con la version vigente (ya
            // la nueva). Reusa la logica existente para el HTML, no la duplica -- pero su calculo
            // de status ignora el caso legado sin slots (solo mira huecos de campo en el html, no
            // signed_at), asi que se refuerza 'pendiente' despues: este flujo es quien sabe con
            // certeza que el documento se acaba de reabrir para re-firma.
            $this->packageService->regenerateOne($documento->fresh());
            $documento->fresh()->update(['status' => 'pendiente']);
        }

        return $documentos->count();
    }

    /**
     * Item roadmap #9990818 (q3 de #9990806, ya aprobada por Irving: notificación in-app +
     * badge). Fuente de verdad = talento_employee_document_reaperturas (append-only), SIN tabla
     * de notificaciones nueva: un acuse cuenta como "reabierto pendiente" si su reapertura MÁS
     * RECIENTE no tiene, después de ella, ninguna firma (columna legado `signed_at` o fila de
     * `talento_employee_document_signatures` -- reopenForNewVersion() borra ambas al reabrir, así
     * que "sin firma posterior" == "todavía no se volvió a firmar desde que se reabrió").
     *
     * @return Collection<int, array{employee_document_id:int, template_nombre:string, reabierto_at:\Illuminate\Support\Carbon}>
     */
    public function pendientesDeRefirma(int $colaboradorId): Collection
    {
        $documentos = TalentoEmployeeDocument::where('colaborador_id', $colaboradorId)
            ->whereHas('reaperturas')
            ->with(['template:id,name', 'signatures'])
            ->get();

        if ($documentos->isEmpty()) {
            return collect();
        }

        $ultimaReaperturaPorDoc = TalentoEmployeeDocumentReapertura::whereIn('employee_document_id', $documentos->pluck('id'))
            ->selectRaw('employee_document_id, MAX(created_at) as ultima_reapertura_at')
            ->groupBy('employee_document_id')
            ->pluck('ultima_reapertura_at', 'employee_document_id');

        return $documentos
            ->filter(function (TalentoEmployeeDocument $doc) use ($ultimaReaperturaPorDoc) {
                $reaperturaAt = $ultimaReaperturaPorDoc->get($doc->id);
                if (!$reaperturaAt) {
                    return false;
                }

                $firmaMasReciente = collect([$doc->signed_at, $doc->signatures->max('signed_at')])
                    ->filter()
                    ->max();

                return !$firmaMasReciente || $firmaMasReciente->lt($reaperturaAt);
            })
            ->map(fn (TalentoEmployeeDocument $doc) => [
                'employee_document_id' => $doc->id,
                'template_nombre' => $doc->template->name ?? '—',
                'reabierto_at' => $ultimaReaperturaPorDoc->get($doc->id),
            ])
            ->values();
    }
}
