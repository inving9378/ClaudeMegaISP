<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * DISPARADOR del botón "Jalar trabajo ahora" (#566).
 *
 * HISTORIA — leer antes de tocar: este comando FUE un picker que lanzaba UNA vuelta a la vez y
 * competía con el scheduler paralelo, fijando el circuito en 1 (#432 B1). Se vació a NO-OP con la
 * regla «dispatch = SOLO el scheduler». Esa regla SIGUE VIGENTE y aquí se respeta: este comando
 * **no lanza vueltas ni reclama items**. Lo único que hace es INVOCAR a `circuito:scheduler`, que
 * sigue siendo el único despachador. Correrlo de más es inofensivo: el scheduler tiene su propio
 * flock y una segunda invocación simultánea se va sin hacer nada.
 *
 * POR QUÉ existe de nuevo: el botón solo dejaba una bandera que el scheduler consumía en su
 * corrida del minuto siguiente, así que "jalar trabajo ahora" tardaba hasta 60 s. Este watcher
 * corre como `meganet` (el usuario dueño de los worktrees y del runtime — www-data no puede
 * escribir `.git`) y sondea la bandera cada pocos segundos, así que el botón se siente inmediato.
 *
 * Su línea de cron ya existe: `circuito:disparo-check --watch=58 --poll=3`.
 */
class DisparoCheckCommand extends Command
{
    protected $signature = 'circuito:disparo-check
        {--watch=0 : segundos que se queda sondeando (0 = una sola pasada)}
        {--poll=3 : segundos entre sondeos}';

    protected $description = 'Vigila el disparo manual y adelanta una corrida de circuito:scheduler (no despacha por su cuenta).';

    public function handle(RoadmapCircuitoService $svc): int
    {
        $watch = max(0, (int) $this->option('watch'));
        $poll  = max(1, (int) $this->option('poll'));
        $hasta = time() + $watch;

        do {
            // Kill switch: en pausa no se adelanta nada (el scheduler tampoco haría nada).
            if (! $svc->isPaused() && $svc->pendingDisparo()) {
                $this->info('Disparo pendiente detectado → adelantando una corrida del scheduler.');

                // El scheduler consume la bandera y hace el reparto. Si otra corrida ya lo tiene
                // tomado por flock, esta se va sin hacer nada: no hay carrera posible.
                try {
                    Artisan::call('circuito:scheduler');
                    $this->line(trim(Artisan::output()));
                } catch (\Throwable $e) {
                    $this->warn('No se pudo adelantar el scheduler: ' . $e->getMessage());
                }
            }

            if (time() >= $hasta) {
                break;
            }
            sleep($poll);
        } while (time() < $hasta);

        return self::SUCCESS;
    }
}
