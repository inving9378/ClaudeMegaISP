<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Console\Command;

/**
 * Imprime los flags del circuito en formato machine-readable (para el wrapper vuelta.sh):
 *   pausado=<0|1>
 *   modo=<aviso_previo|autonomo>
 */
class FlagsCommand extends Command
{
    protected $signature = 'circuito:flags';

    protected $description = 'Imprime los flags del circuito (pausado, modo) para el ejecutor on-box.';

    public function handle(RoadmapCircuitoService $svc): int
    {
        $this->line('pausado=' . ($svc->isPaused() ? '1' : '0'));
        $this->line('modo=' . $svc->getModo());

        return self::SUCCESS;
    }
}
