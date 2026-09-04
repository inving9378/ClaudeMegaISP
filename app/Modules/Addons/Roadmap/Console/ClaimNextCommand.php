<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Console\Command;

/**
 * Pool CONTINUO (#334 F1): un worker de slot, al terminar su item, llama a esto para RECLAMAR el
 * siguiente item elegible SIN esperar al cron. Imprime SOLO el id reclamado (o nada si no hay
 * trabajo / pausa). SERIALIZADO por flock (claim.lock) → dos workers nunca toman items del mismo
 * módulo a la vez. Respeta el kill switch (pausa → no reclama).
 *
 * #198 — `--item=N`: DESPACHO DIRIGIDO. En vez de dejar que el picker elija, reclama ESE item
 * concreto para este worker (si sigue elegible — mismo candado atómico de siempre; si no, no
 * reclama nada, igual que "no había trabajo"). Mismo contrato de salida (id o nada).
 */
class ClaimNextCommand extends Command
{
    protected $signature = 'circuito:claim-next {--sid= : id del worker (traza)} {--item= : despacho dirigido de un item concreto (#198), salta el picker}';

    protected $description = 'Reclama atómicamente el siguiente item elegible para un worker del pool (#334 F1).';

    private const CLAIM_LOCK = '/home/meganet/circuito/claim.lock';

    public function handle(RoadmapCircuitoService $svc): int
    {
        $lock = @fopen(self::CLAIM_LOCK, 'c');
        if (! $lock || ! flock($lock, LOCK_EX)) {
            return self::SUCCESS; // sin lock → no reclama (imprime nada)
        }
        try {
            $item = $this->option('item');
            $id = $svc->claimNextParalelo($this->option('sid'), $item !== null ? (int) $item : null);
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
