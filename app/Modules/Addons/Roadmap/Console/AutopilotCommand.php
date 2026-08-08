<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\AutopilotService;
use Illuminate\Console\Command;

/**
 * Barrido del AUTOPILOT (#507 sub-paso 2).
 *
 * El camino normal es automático: el autopilot se dispara solo al escribirse cada brief
 * (RevisorService::aplicarPreguntas). Este comando existe para tres cosas:
 *   1. `--dry` — ver qué decidiría sobre la bandeja actual SIN escribir nada (la forma de auditar
 *      la política antes de aflojarla).
 *   2. recoger items cuyo brief se escribió ANTES de que existiera el autopilot.
 *   3. recoger los que quedaron esperando por la ventana de gracia (si se configura > 0).
 */
class AutopilotCommand extends Command
{
    protected $signature = 'circuito:autopilot
        {--dry : solo reporta qué decidiría, no escribe}
        {--limit=25 : máximo de items a evaluar}
        {--id= : evalúa un solo item por id}';

    protected $description = 'Aplica la política del autopilot sobre la bandeja: decide lo respaldado, deja lo demás para Irving (#507).';

    public function handle(AutopilotService $autopilot): int
    {
        $dry = (bool) $this->option('dry');

        if (! $autopilot->enabled()) {
            $this->warn('Autopilot APAGADO (circuito.autopilot.enabled=false). Nada que hacer.');

            return self::SUCCESS;
        }

        $this->line(sprintf(
            'Política: tope nivel %s · confianza mínima %s · exige reversible %s · gracia %d min',
            strtoupper((string) config('circuito.autopilot.max_nivel', 'B')),
            strtolower((string) config('circuito.autopilot.umbral_confianza', 'alta')),
            config('circuito.autopilot.requiere_reversible', true) ? 'sí' : 'no',
            (int) config('circuito.autopilot.ventana_gracia', 0),
        ));

        $items = $this->option('id')
            ? RoadmapItem::where('id', (int) $this->option('id'))->get()
            : RoadmapItem::bandeja()->ordered()->limit((int) $this->option('limit'))->get();

        if ($items->isEmpty()) {
            $this->info('Sin items que evaluar.');

            return self::SUCCESS;
        }

        $auto = 0;
        $motivos = [];

        foreach ($items as $item) {
            // El dry-run audita aunque el circuito esté pausado (no escribe nada); la aplicación
            // real sí respeta el kill switch.
            $r = $dry ? $autopilot->evaluar($item, true) : $autopilot->aplicar($item);

            if ($r['auto']) {
                $auto++;
                $this->info(sprintf('  %s#%d [%s] → %s · %s',
                    $dry ? 'DRY ' : '', $item->id, $item->nivel_riesgo ?: '—',
                    $dry ? 'SE AUTO-EJECUTARÍA' : ('auto-ejecutado (' . $r['estado'] . ')'),
                    $r['detalle']));
            } else {
                $motivos[$r['motivo']] = ($motivos[$r['motivo']] ?? 0) + 1;
                $this->line(sprintf('   #%d [%s] → Irving · %s',
                    $item->id, $item->nivel_riesgo ?: '—', $r['detalle']));
            }
        }

        $this->newLine();
        $this->info(sprintf('%s%d de %d %s al autopilot; %d quedan para Irving.',
            $dry ? 'DRY: ' : '', $auto, $items->count(),
            $dry ? 'calificarían' : 'tomados', $items->count() - $auto));

        if ($motivos) {
            arsort($motivos);
            $this->line('Por qué quedan para Irving: ' . collect($motivos)
                ->map(fn ($n, $m) => "{$m}={$n}")->implode(' · '));
        }

        return self::SUCCESS;
    }
}
