<?php

namespace App\Console\Commands\Active;

use App\Services\Metricas\PurgaMetricas;
use Illuminate\Console\Command;

/** Item #988: promedia y borra lo vencido del monitoreo de dedicados. Corre a diario, aunque el monitoreo esté apagado. */
class PurgarMetricasCommand extends Command
{
    protected $signature = 'metricas:purgar {--dry-run : solo cuenta lo que se borraría; no promedia ni borra}';

    protected $description = 'Promedia a 5 minutos las métricas de dedicados y borra las que ya vencieron';

    public function handle(PurgaMetricas $purga): int
    {
        $r = $purga->correr((bool) $this->option('dry-run'));

        $this->info(($this->option('dry-run') ? 'Se borrarían: ' : 'Borrado: ')
            . "{$r['cliente_metricas']} lecturas por minuto, {$r['cliente_metricas_ping']} pings, "
            . "{$r['cliente_metricas_5min']} promedios y {$r['metricas_colector_latidos']} latidos."
            . ($this->option('dry-run') ? '' : " Promedios escritos: {$r['promedios']}."));

        return self::SUCCESS;
    }
}
