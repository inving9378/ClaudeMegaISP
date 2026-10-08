<?php

namespace App\Modules\Addons\MonitoreoOficial\Jobs;

use App\Modules\Addons\MonitoreoOficial\Models\MonitoringCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fase 4 ("scheduler/colas"): una revisión por expediente, en cola — nunca inline
 * en el comando que los despacha a todos.
 *
 * Ningún adaptador de scraping (SIGA/VIDOC/gaceta/url) existe todavía (eso es F5+);
 * este job NO finge revisarlos. Solo hace bookkeeping real: sella "última
 * comprobación" y calcula "próxima revisión". Para fuentes `manual_pdf` eso es
 * exactamente lo que corresponde (la revisión es manual, por diseño). Para el
 * resto, deja constancia explícita de que falta el adaptador — nunca un silencio
 * que parezca "ya se revisó" sin haber revisado nada.
 */
class ReviewMonitoringCaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(public int $caseId)
    {
    }

    public function handle(): void
    {
        $case = MonitoringCase::with('source')->find($this->caseId);
        if (! $case) {
            return; // se borró entre que se encoló y que corrió — no es un error
        }

        $tipo = $case->source?->source_type;

        if ($tipo !== null && $tipo !== 'manual_pdf') {
            Log::info("[MonitoreoOficial] Revisión omitida para expediente #{$case->id}: "
                . "la fuente es tipo '{$tipo}', sin adaptador de descarga automática implementado todavía.");
        }

        $case->update([
            'last_checked_at' => now(),
            'next_check_at'   => now()->addDays(7),
        ]);
    }
}
