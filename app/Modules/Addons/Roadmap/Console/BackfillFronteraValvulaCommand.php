<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;

/**
 * Fase 3 (#976, sub-item de #905) — BACKFILL de `frontera_valvula` para los items existentes.
 *
 * La Fase 2 (#975) hizo que `RevisorService::aplicarTriajeNull()` selle `frontera_valvula` en
 * cada veredicto NUEVO de la válvula de contexto, pero eso no toca los items cuyo veredicto ya
 * quedó escrito en `roadmap_items.log` ANTES de ese fix. Este comando recorre el log de esos
 * items y sella la columna con el mismo criterio que ya aplica el camino de escritura.
 *
 * Recorre SOLO items con `frontera_valvula` NULL (no toca los que la válvula de nacimiento ya
 * selló). Busca en `log` las entradas `evento==='valvula_contexto'` con `veredicto` explícito
 * ('mencion' o 'accion') y toma la MÁS RECIENTE por `ts`. Soporta --dry-run.
 */
class BackfillFronteraValvulaCommand extends Command
{
    protected $signature = 'circuito:backfill-frontera-valvula
        {--sid= : tu slot de terminal (wt-K), solo para el log}
        {--dry-run : solo reporta cuántos se sellarían, sin escribir}';

    protected $description = 'Fase 3 (#976) — backfill de frontera_valvula desde roadmap_items.log (eventos valvula_contexto con veredicto explícito).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $mencion = 0;
        $accion = 0;

        RoadmapItem::query()
            ->whereNull('frontera_valvula')
            ->whereNotNull('log')
            ->select(['id', 'log'])
            ->chunkById(200, function ($items) use ($dryRun, &$mencion, &$accion) {
                foreach ($items as $item) {
                    $log = is_array($item->log) ? $item->log : [];
                    $mejor = null;

                    foreach ($log as $entrada) {
                        if (! is_array($entrada)) {
                            continue;
                        }
                        if (($entrada['evento'] ?? null) !== 'valvula_contexto') {
                            continue;
                        }
                        $veredicto = $entrada['veredicto'] ?? null;
                        if ($veredicto !== 'mencion' && $veredicto !== 'accion') {
                            continue;
                        }
                        if ($mejor === null || (string) ($entrada['ts'] ?? '') > (string) ($mejor['ts'] ?? '')) {
                            $mejor = $entrada;
                        }
                    }

                    if ($mejor === null) {
                        continue;
                    }

                    $mejor['veredicto'] === 'mencion' ? $mencion++ : $accion++;

                    if (! $dryRun) {
                        $item->frontera_valvula = $mejor['veredicto'];
                        $item->frontera_valvula_at = now();
                        $item->save();
                    }
                }
            });

        $total = $mencion + $accion;
        $verbo = $dryRun ? 'Se sellarían' : 'Sellados';

        $this->info("{$verbo}: {$total} (mencion={$mencion}, accion={$accion})");

        if ($dryRun) {
            $this->comment('Modo --dry-run: no se escribió nada.');
        }

        return self::SUCCESS;
    }
}
