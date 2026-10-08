<?php

namespace App\Modules\Addons\MonitoreoOficial\Console;

use App\Modules\Addons\MonitoreoOficial\Services\ImpiDemoSeeder;
use App\Support\RolInstancia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Importación EXPRESA del expediente de ejemplo de MEGANET (IMPI 2806655).
 *
 * Es la única vía para cargar ese expediente fuera de la instancia MEGANET.
 * En una VM de otro ISP, por defecto rechaza (ese dato no es suyo) salvo que se
 * pase --force, dejando la decisión como un acto explícito y auditable.
 */
class ImportDemoImpiCommand extends Command
{
    protected $signature = 'monitoring:import-demo-impi {--force : Importar aunque la instancia no sea MEGANET (operador)}';

    protected $description = 'Importa (idempotente) el expediente de ejemplo IMPI 2806655 de MEGANET. Solo MEGANET por defecto; otras instancias requieren --force.';

    public function handle(ImpiDemoSeeder $seeder): int
    {
        if (! Schema::hasTable('monitoring_cases')) {
            $this->error('El módulo Monitoreo Oficial no está instalado (falta la tabla monitoring_cases).');
            return self::FAILURE;
        }

        if (! RolInstancia::esOperador() && ! $this->option('force')) {
            $this->warn('Esta instancia no es MEGANET (operador). El expediente 2806655 es dato de MEGANET.');
            $this->line('Si realmente quieres importarlo aquí, repite con --force.');
            return self::FAILURE;
        }

        $r = $seeder->seed($this->option('force') ? 'comando-force' : 'comando-operador');

        $this->info($r['created']
            ? "Expediente IMPI 2806655 importado (case_id={$r['case_id']})."
            : "El expediente IMPI 2806655 ya existía (case_id={$r['case_id']}); no se duplicó.");

        return self::SUCCESS;
    }
}
