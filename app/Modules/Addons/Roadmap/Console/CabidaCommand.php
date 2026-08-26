<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\ThomasService;
use Illuminate\Console\Command;

/**
 * #895 — ¿el item cabe en una vuelta, o hay que descomponerlo ANTES de picar código?
 *
 * Primer paso de la terminal (antes de `circuito:rama`): si esto dice que NO cabe, la terminal
 * NO empieza a implementar — usa `circuito:sub-item` para dejar fases registradas y termina su
 * vuelta. La decisión vive en `ThomasService::caberEnVuelta()` (aquí solo se imprime/traduce a
 * exit code); ver ahí el porqué de las tres señales que usa y por qué es deliberadamente
 * conservador (nunca dispara con el bucket heurístico sin muestras reales).
 *
 * Exit 0 = cabe (procede). Exit 1 = NO cabe (descompón). Idempotente vía `yaFueDescompuesto()`.
 */
class CabidaCommand extends Command
{
    protected $signature = 'circuito:cabida
        {id : ID del item a evaluar}
        {--sid= : tu slot de terminal (wt-K), solo para el log}';

    protected $description = '#895 — decide si un item cabe en una vuelta o si hay que descomponerlo antes de arrancar.';

    public function handle(ThomasService $thomas): int
    {
        $item = RoadmapItem::find($this->argument('id'));
        if (! $item) {
            $this->error('Item no encontrado.');

            return self::FAILURE;
        }

        $r = $thomas->caberEnVuelta($item);
        $eta = $r['eta_segundos'] !== null ? " (histórico ~{$r['eta_segundos']}s)" : '';

        if ($r['cabe']) {
            $this->info("CABE [{$r['motivo']}]{$eta} — procede a implementar #{$item->id}.");

            return self::SUCCESS;
        }

        $this->error(
            "NO CABE [{$r['motivo']}]{$eta} — no piques código todavía: descompón con "
            . "`circuito:sub-item {$item->id} --sid=" . ($this->option('sid') ?: 'wt-K')
            . " --titulo=... --spec=...` y termina la vuelta con ejecuto=false."
        );

        return self::FAILURE;
    }
}
