<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;

/**
 * REAPER de items atascados en `en_progreso` (#334 F1). En el pool continuo, un worker que muere o
 * timeouté (600s) deja su item RECLAMADO (en_progreso) sin soltar → bloquea el item y su módulo, y
 * seca el pool. Este comando libera los en_progreso viejos (updated_at > --minutes) que NO los trabaja
 * un humano (en_desarrollo_humano=false) → los manda a la bandeja de Irving (requiere_irving) con nota.
 * NUNCA toca los que un humano trabaja (#341). Cron cada pocos minutos.
 */
class ReapStuckCommand extends Command
{
    protected $signature = 'circuito:reap-stuck {--minutes=25 : antigüedad mínima en en_progreso para liberar}';

    protected $description = 'Libera items atascados en en_progreso por workers muertos/timeout (#334 F1).';

    public function handle(): int
    {
        $mins = max(5, (int) $this->option('minutes'));
        $corte = now()->subMinutes($mins);

        // #507 sub-paso 3 — LEASE EXPLÍCITO: se libera solo si AMBAS señales están frías, el latido
        // del worker (`claimed_at`, que renueva `circuito:vivo --watch`) y cualquier escritura sobre
        // el item (`updated_at`). Antes bastaba con `updated_at`, y eso mataba workers VIVOS que
        // simplemente llevaban rato sin escribir en el item. Los items reclamados antes de que
        // existiera la columna traen `claimed_at` null y se evalúan como siempre (solo updated_at).
        $stuck = RoadmapItem::where('estado_aprobacion', 'en_progreso')
            ->where('en_desarrollo_humano', false)
            ->where('updated_at', '<', $corte)
            ->where(function ($q) use ($corte) {
                $q->whereNull('claimed_at')->orWhere('claimed_at', '<', $corte);
            })
            ->get();

        foreach ($stuck as $i) {
            $i->estado_aprobacion  = 'requiere_irving';
            $i->claimed_at         = null;   // el lease muere con el reclamo que lo sostenía
            $i->comentarios_claude = (string) $i->comentarios_claude
                . "\n[reaper] worker murió/timeout con el item en en_progreso ({$i->updated_at->diffForHumans()}) → liberado a tu bandeja para revisión.";
            $i->save();
            $this->line("#{$i->id} liberado (estaba en_progreso desde {$i->updated_at->diffForHumans()}).");
        }

        $this->info('Reaper: ' . $stuck->count() . ' item(s) liberado(s) de en_progreso.');

        return self::SUCCESS;
    }
}
