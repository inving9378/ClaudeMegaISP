<?php

namespace App\Console\Commands\Active;

use App\Modules\Addons\CrmEmpresarial\Services\PropuestaService;
use Illuminate\Console\Command;

/** Vence las propuestas CRM-E cuya vigencia ya pasó (item #562). */
class VencerPropuestasCrmeCommand extends Command
{
    protected $signature = 'crme:propuestas-vencer';
    protected $description = 'Marca como vencidas las propuestas CRM Empresarial con vigencia cumplida';

    public function handle(PropuestaService $svc): int
    {
        $this->info('Propuestas vencidas: ' . $svc->vencerVencidas());

        return self::SUCCESS;
    }
}
