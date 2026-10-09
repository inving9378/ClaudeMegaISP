<?php

namespace App\Modules\Addons\MonitoreoOficial\Jobs;

use App\Modules\Addons\MonitoreoOficial\Models\MonitoringCaseDocument;
use App\Modules\Addons\MonitoreoOficial\Services\DocumentAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fase 5 ("IA"): analiza UN documento ya subido, en cola — nunca inline en el
 * request de subida (una llamada a IA puede tardar varios segundos). Solo se
 * despacha para documentos genuinamente nuevos (CaseDocumentService ya filtró
 * los idénticos en la Fase 3 — no tiene caso gastar IA en releer el mismo PDF).
 */
class AnalyzeCaseDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(public int $documentId)
    {
    }

    public function handle(DocumentAnalysisService $service): void
    {
        $documento = MonitoringCaseDocument::find($this->documentId);
        if (! $documento) {
            return; // se borró entre que se encoló y que corrió
        }

        // El "anterior" es el documento previo de ESTE expediente, por orden de alta
        // (nunca el que se acaba de analizar).
        $anterior = MonitoringCaseDocument::where('case_id', $documento->case_id)
            ->where('id', '<', $documento->id)
            ->orderByDesc('id')
            ->first();

        $resultado = $service->analizar($documento, $anterior);

        $documento->update([
            'ai_summary' => $resultado['resumen'],
            'ai_change_summary' => $resultado['que_cambio'],
            'ai_has_relevant_change' => $resultado['cambio_relevante'],
            'ai_analyzed_at' => now(),
            'ai_error' => $resultado['error'],
        ]);
    }
}
