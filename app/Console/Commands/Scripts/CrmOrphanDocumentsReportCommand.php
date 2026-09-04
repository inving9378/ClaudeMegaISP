<?php

namespace App\Console\Commands\Scripts;

use App\Modules\Core\CRM\Support\CrmOrphanDocumentDetector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Reporte de documentos CRM huérfanos por archivo faltante (#81/#9990084).
 *
 * Decisión oficial de Irving (item #81, 2026-09-03): solo IDENTIFICAR y
 * reportar, NO borrar nada. Criterio = el path del documento no existe en
 * disco (CrmDocumentStorage::fileExists() falso), vía CrmOrphanDocumentDetector.
 *
 * 100% de solo lectura: no borra filas ni archivos bajo ningún flag.
 */
class CrmOrphanDocumentsReportCommand extends Command
{
    protected $signature = 'crm:documentos-huerfanos
        {--limit=200 : Máximo de huérfanos a reportar}';

    protected $description = 'Reporta (solo lectura) document_crms cuyo archivo físico no existe en disco. #81';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $orphans = CrmOrphanDocumentDetector::detect($limit);

        if ($orphans->isEmpty()) {
            $this->info('Sin documentos huérfanos. ✔');
            return self::SUCCESS;
        }

        $this->warn("Documentos CRM huérfanos detectados: {$orphans->count()}");

        $rows = [];
        foreach ($orphans as $o) {
            $this->line("  - id={$o->id} crm_id={$o->crm_id} title=\"{$o->title}\" motivo={$o->motivo}");
            Log::warning("[crm:documentos-huerfanos] huérfano detectado id={$o->id} crm_id={$o->crm_id} title=\"{$o->title}\" motivo={$o->motivo}");
            $rows[] = [$o->id, $o->crm_id, $o->title, $o->motivo];
        }

        $dir = storage_path('app/reportes');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $csvPath = $dir . '/documentos-huerfanos-' . now()->format('Ymd_His') . '.csv';
        $fh = fopen($csvPath, 'w');
        fputcsv($fh, ['id', 'crm_id', 'title', 'motivo']);
        foreach ($rows as $row) {
            fputcsv($fh, $row);
        }
        fclose($fh);

        $this->info("CSV escrito en: {$csvPath}");

        return self::SUCCESS;
    }
}
