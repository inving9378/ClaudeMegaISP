<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapAdjunto;
use App\Modules\Addons\Roadmap\Services\RoadmapAdjuntoService;
use Illuminate\Console\Command;

/**
 * #9991163 (punto 5) — Un adjunto borrado desde la Torre es borrado LÓGICO; el archivo se
 * conserva DIAS_RETENCION (30) días por si hay que recuperarlo (re-subirlo lo revive con el mismo
 * id). Este comando, agendado a diario en Kernel.php, quita de disco los que ya vencieron.
 * `--dry-run` solo lista.
 */
class AdjuntosPurgarCommand extends Command
{
    protected $signature = 'roadmap:adjuntos-purgar {--dry-run : solo lista lo que se quitaría de disco}';

    protected $description = '#9991163 — quita de disco los adjuntos borrados hace más de ' . RoadmapAdjunto::DIAS_RETENCION . ' días.';

    public function handle(RoadmapAdjuntoService $svc): int
    {
        $vencidos = RoadmapAdjunto::onlyTrashed()->whereNull('disco_purgado_at')->where('purgar_despues_de', '<=', now())->get();
        if ($vencidos->isEmpty()) {
            $this->info('Nada que purgar.');

            return self::SUCCESS;
        }
        foreach ($vencidos as $a) {
            $this->line(sprintf('#%d %s (%s) borrado %s → %s', $a->id, $a->nombre_original, $a->ruta, $a->deleted_at?->format('Y-m-d'), $this->option('dry-run') ? 'se quitaría' : 'se quita'));
        }
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }
        $n = $svc->purgarVencidos();
        $this->info("{$n} archivo(s) quitado(s) de disco (los registros quedan, marcados con disco_purgado_at).");

        return self::SUCCESS;
    }
}
