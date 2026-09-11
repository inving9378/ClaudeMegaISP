<?php

namespace App\Modules\Addons\Ventas\Console;

use App\Modules\Addons\Ventas\Services\CustodiaService;
use Illuminate\Console\Command;

/**
 * Job programado del item #9990780 (decisión q4: cron diario a las 00:05).
 */
class LiberarCustodiasVencidasCommand extends Command
{
    protected $signature = 'ventas:liberar-custodias-vencidas';

    protected $description = 'Marca custodias vencidas y regresa al pool general las que agotaron sus 3 renovaciones (item #9990780)';

    public function handle(CustodiaService $service): int
    {
        $resultado = $service->liberarVencidas();

        $this->info("Custodias marcadas como vencidas (con renovación disponible): {$resultado['marcadas_vencidas']}");
        $this->info("Custodias liberadas al pool general (sin renovación disponible): {$resultado['liberadas']}");

        return self::SUCCESS;
    }
}
