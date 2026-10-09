<?php

namespace App\Modules\Addons\MonitoreoOficial\Services;

use App\Modules\Addons\MonitoreoOficial\Jobs\AnalyzeCaseDocumentJob;
use App\Modules\Addons\MonitoreoOficial\Models\MonitoringCase;
use App\Modules\Addons\MonitoreoOficial\Models\MonitoringCaseDocument;
use Illuminate\Http\UploadedFile;

/**
 * Fase 3 ("fuentes + PDF manual"): alta manual de documentos oficiales contra un
 * expediente — el primer ladrillo de "detección de cambios". Guarda el PDF y
 * compara su huella (sha256) contra el último documento subido para el mismo
 * expediente. Desde la Fase 5, además encola el análisis por IA del documento
 * (solo si es genuinamente nuevo — releer un PDF idéntico sería gastar IA sin
 * ganar nada nuevo).
 */
class CaseDocumentService
{
    /** @return array{document: MonitoringCaseDocument, es_identico_al_anterior: bool} */
    public function subir(MonitoringCase $case, UploadedFile $file, ?int $userId, ?string $notes = null): array
    {
        $hash = hash_file('sha256', $file->getRealPath());

        $ultimo = $case->documents()->first();
        $esIdenticoAlAnterior = $ultimo && hash_equals($ultimo->file_hash, $hash);

        // Nombre generado por Laravel (store sin nombre propio): nunca el nombre
        // original del usuario en el path — evita colisiones y cualquier intento
        // de path traversal vía el nombre de archivo.
        $path = $file->store("monitoreo-oficial/expedientes/{$case->id}", 'local');

        $document = MonitoringCaseDocument::create([
            'case_id' => $case->id,
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $path,
            'file_hash' => $hash,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'notes' => $notes,
            'uploaded_by' => $userId,
        ]);

        // Solo se marca "cambió" si el documento es de verdad distinto al último
        // que se tenía — si alguien vuelve a subir el mismo PDF, queda en el
        // historial (auditoría) pero no dispara una falsa señal de cambio.
        if (! $esIdenticoAlAnterior) {
            $case->update(['last_change_at' => now()]);
            AnalyzeCaseDocumentJob::dispatch($document->id);
        }

        return ['document' => $document, 'es_identico_al_anterior' => $esIdenticoAlAnterior];
    }
}
