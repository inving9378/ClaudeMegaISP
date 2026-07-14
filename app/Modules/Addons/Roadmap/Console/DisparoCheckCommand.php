<?php

namespace App\Modules\Addons\Roadmap\Console;

use Illuminate\Console\Command;

/**
 * DEPRECADO (#432 B1). Era el picker on-box del disparo manual que lanzaba UNA vuelta a la vez
 * ("no solapar" + backlog en wt-exec) → fijaba el circuito en 1 y competía con el scheduler
 * paralelo. Se ELIMINÓ desde la raíz: ahora el ÚNICO despachador es `circuito:scheduler`, que
 * además drena la cola de merges y consume el flag de disparo. Este comando quedó como NO-OP para
 * no romper un cron viejo que todavía lo invoque; su línea de cron se retira en el cierre.
 *
 * NO reintroducir un lanzador de vueltas aquí: dispatch = SOLO el scheduler (regla única
 * no-colisión, N en paralelo).
 */
class DisparoCheckCommand extends Command
{
    protected $signature = 'circuito:disparo-check {--watch=0 : (ignorado, deprecado)} {--poll=3 : (ignorado, deprecado)}';

    protected $description = 'DEPRECADO — el despacho y el drenado de merges viven en circuito:scheduler.';

    public function handle(): int
    {
        $this->warn('circuito:disparo-check está DEPRECADO (#432 B1): el único despachador es '
            . 'circuito:scheduler (paralelo), que también drena merges y consume el disparo. No-op.');

        return self::SUCCESS;
    }
}
