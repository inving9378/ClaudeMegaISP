<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Console\Command;

/**
 * REAPER de items atascados en `en_progreso` (#334 F1). En el pool continuo, un worker que muere o
 * timeouté (600s) deja su item RECLAMADO (en_progreso) sin soltar → bloquea el item y su módulo, y
 * seca el pool. Este comando libera los en_progreso viejos (updated_at > --minutes) que NO los trabaja
 * un humano (en_desarrollo_humano=false).
 *
 * #561 — en vez de escalar directo a la bandeja de Irving, RE-ENCOLA el item (vuelve al estado
 * aprobado que tenía antes de reclamarse, el pool lo vuelve a tomar) vía
 * `RoadmapCircuitoService::reencolarHuerfano`, que solo escala a `requiere_irving` tras superar el
 * tope de reclamos fallidos del mismo item (evita ciclar infinito en uno genuinamente roto).
 * NUNCA toca los que un humano trabaja (#341). Cron cada pocos minutos.
 */
class ReapStuckCommand extends Command
{
    protected $signature = 'circuito:reap-stuck {--minutes=25 : antigüedad mínima en en_progreso para liberar}';

    protected $description = 'Re-encola (o escala tras N fallos) items atascados en en_progreso por workers muertos/timeout (#334 F1 / #561).';

    public function handle(RoadmapCircuitoService $svc): int
    {
        $mins = max(5, (int) $this->option('minutes'));
        $corte = now()->subMinutes($mins);

        // ── VÍA RÁPIDA (#566): liberar en MINUTOS, no en 25 ────────────────────────────────
        // El camino lento de abajo espera a que DOS timestamps se enfríen, y mientras tanto un
        // huérfano con footprint desconocido ("Sin clasificar") tiene parada a TODA la flota,
        // porque por diseño (#432 B2) ese footprint corre solo. Media hora de seis terminales
        // ociosas por un item que ya nadie trabaja.
        //
        // Aquí no se adivina por tiempo: se pregunta por el FLOCK del slot, que el kernel suelta
        // aunque el proceso muera de golpe. Si el slot está libre, no hay vuelta corriendo ahí y
        // el reclamo es huérfano, punto. La gracia corta solo evita pisar una vuelta que apenas
        // arranca (el scheduler reclama el item ANTES de que vuelta.sh tome el flock).
        $gracia = max(1, (int) config('circuito.reaper.gracia_minutos', 3));
        $huerfanos = RoadmapItem::where('estado_aprobacion', 'en_progreso')
            ->where('en_desarrollo_humano', false)
            ->whereNotNull('worker_sid')
            ->where('claimed_at', '<', now()->subMinutes($gracia))
            ->get()
            ->filter(fn (RoadmapItem $i) => $svc->slotLibre((string) $i->worker_sid));

        $rapidos = 0;
        foreach ($huerfanos as $i) {
            $motivo = "el slot {$i->worker_sid} está libre (ninguna vuelta corriendo ahí): reclamo huérfano";
            $r = $svc->reencolarHuerfano($i, 'reaper-rapido', $motivo);
            $rapidos++;
            $this->line($r['resultado'] === 'escalado'
                ? "#{$i->id} escalado a tu bandeja tras {$r['reap_count']} reclamos fallidos (tope {$r['tope']})."
                : "#{$i->id} re-encolado por slot libre → {$r['estado']} (intento {$r['reap_count']}/{$r['tope']}).");
        }
        if ($rapidos > 0) {
            $this->info("Reaper rápido: {$rapidos} huérfano(s) detectado(s) por slot libre.");
        }

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

        $reencolados = 0;
        $escalados   = 0;
        foreach ($stuck as $i) {
            $motivo = "worker murió/timeout con el item en en_progreso ({$i->updated_at->diffForHumans()})";
            $r = $svc->reencolarHuerfano($i, 'reaper', $motivo);
            if ($r['resultado'] === 'escalado') {
                $escalados++;
                $this->line("#{$i->id} escalado a tu bandeja tras {$r['reap_count']} reclamos fallidos (tope {$r['tope']}).");
            } else {
                $reencolados++;
                $this->line("#{$i->id} re-encolado → {$r['estado']} (intento {$r['reap_count']}/{$r['tope']}).");
            }
        }

        $this->info("Reaper: {$reencolados} item(s) re-encolado(s), {$escalados} escalado(s) a la bandeja de Irving.");

        return self::SUCCESS;
    }
}
