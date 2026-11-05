<?php

namespace App\Console\Commands\Active;

use App\Services\Metricas\AsegurarMonitoreoDedicados;
use Illuminate\Console\Command;

/** Item #988: tarea diaria que crea el monitoreo que le falte a un dedicado y corrige pausas. */
class AsegurarMonitoreoDedicadosCommand extends Command
{
    protected $signature = 'metricas:asegurar-dedicados';

    protected $description = 'Crea la configuración de monitoreo que le falte a los servicios dedicados y los pausa o reanuda según su estado';

    public function handle(AsegurarMonitoreoDedicados $asegurar): int
    {
        $r = $asegurar->todos();
        $this->info("Creados: {$r['creados']} · pausados: {$r['pausados']} · reanudados: {$r['reanudados']}");

        return self::SUCCESS;
    }
}
