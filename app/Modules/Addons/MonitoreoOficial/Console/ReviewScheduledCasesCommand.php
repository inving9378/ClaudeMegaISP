<?php

namespace App\Modules\Addons\MonitoreoOficial\Console;

use App\Modules\Addons\MonitoreoOficial\Jobs\ReviewMonitoringCaseJob;
use App\Modules\Addons\MonitoreoOficial\Models\MonitoringCase;
use Illuminate\Console\Command;

/**
 * Fase 4 ("scheduler/colas"): despacha una revisión en cola por cada expediente
 * activo. No revisa nada inline aquí — un solo expediente lento (o sin adaptador)
 * nunca debe frenar a los demás. Se registra en command_configs (schedule
 * dinámico desde BD), no tocando app/Console/Kernel.php — ver migración
 * 2026_10_08_180500_registrar_schedule_revision_monitoreo.
 */
class ReviewScheduledCasesCommand extends Command
{
    protected $signature = 'monitoring:revisar-expedientes {--dry-run : Solo contar, sin encolar nada}';

    protected $description = 'Encola una revisión (sellar última comprobación / próxima revisión) por cada expediente activo de Monitoreo Oficial.';

    public function handle(): int
    {
        $ids = MonitoringCase::where('monitoring_status', 'activo')->pluck('id');

        if ($this->option('dry-run')) {
            $this->info("Dry-run: {$ids->count()} expediente(s) activo(s) se encolarían.");
            return self::SUCCESS;
        }

        foreach ($ids as $id) {
            ReviewMonitoringCaseJob::dispatch($id);
        }

        $this->info("Encolados {$ids->count()} expediente(s) activo(s) para revisión.");
        return self::SUCCESS;
    }
}
