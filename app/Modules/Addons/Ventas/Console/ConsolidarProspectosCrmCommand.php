<?php

namespace App\Modules\Addons\Ventas\Console;

use App\Modules\Addons\Ventas\Services\ProspectoCrmSyncService;
use App\Modules\Core\CRM\Models\CrmLeadInformation;
use Illuminate\Console\Command;

/**
 * Item #9990779 — backfill de una sola vez: proyecta todo `crm_lead_information` existente
 * hacia `ventas_prospectos` (fuente única del motor de ventas). Idempotente (upsert por
 * origen+origen_id vía ProspectoCrmSyncService) — correrlo de nuevo no duplica filas.
 *
 * La doble escritura hacia adelante (nuevos leads / cambios de owner) la cubren los observers
 * registrados en Ventas\ModuleServiceProvider sobre CrmLeadInformation/CrmMainInformation.
 */
class ConsolidarProspectosCrmCommand extends Command
{
    protected $signature = 'ventas:consolidar-prospectos-crm {--dry-run : Solo reporta conteos, no escribe nada}';

    protected $description = 'Consolida crm_lead_information/crm_main_information hacia ventas_prospectos (catálogo único, item #9990779)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $totalCrm = CrmLeadInformation::count();
        $this->info("crm_lead_information antes: {$totalCrm}");

        if ($dryRun) {
            $this->info('--dry-run: no se escribió nada.');

            return self::SUCCESS;
        }

        $creados = 0;
        $actualizados = 0;

        CrmLeadInformation::select('crm_id')->orderBy('crm_id')->chunk(200, function ($leads) use (&$creados, &$actualizados) {
            foreach ($leads as $lead) {
                $existiaAntes = \App\Modules\Addons\Ventas\Models\VentaProspecto::where('origen', 'crm')
                    ->where('origen_id', $lead->crm_id)
                    ->exists();

                ProspectoCrmSyncService::sincronizar($lead->crm_id);

                $existiaAntes ? $actualizados++ : $creados++;
            }
        });

        $totalVentasProspectos = \App\Modules\Addons\Ventas\Models\VentaProspecto::where('origen', 'crm')->count();

        $this->info("Consolidados: {$creados} creados, {$actualizados} actualizados.");
        $this->info("ventas_prospectos (origen=crm) después: {$totalVentasProspectos}");

        return self::SUCCESS;
    }
}
