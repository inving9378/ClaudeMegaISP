<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Console\Command;

/**
 * Pool CONTINUO (#334 F1): un worker de slot, al terminar su item, llama a esto para RECLAMAR el
 * siguiente item elegible SIN esperar al cron. Imprime SOLO el id reclamado (o nada si no hay
 * trabajo / pausa). SERIALIZADO por flock (claim.lock) → dos workers nunca toman items del mismo
 * módulo a la vez. Respeta el kill switch (pausa → no reclama).
 */
class ClaimNextCommand extends Command
{
    protected $signature = 'circuito:claim-next {--sid= : id del worker (traza)}';

    protected $description = 'Reclama atómicamente el siguiente item elegible para un worker del pool (#334 F1).';

    private const CLAIM_LOCK = '/home/meganet/circuito/claim.lock';

    public function handle(RoadmapCircuitoService $svc): int
    {
        $lock = @fopen(self::CLAIM_LOCK, 'c');
        if (! $lock || ! flock($lock, LOCK_EX)) {
            return self::SUCCESS; // sin lock → no reclama (imprime nada)
        }
        try {
            $id = $svc->claimNextParalelo($this->option('sid'));
            if ($id !== null) {
                $this->output->write((string) $id); // SOLO el id, sin salto → fácil de capturar en shell
            }

            return self::SUCCESS;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
