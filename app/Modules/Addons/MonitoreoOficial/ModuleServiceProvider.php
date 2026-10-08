<?php

namespace App\Modules\Addons\MonitoreoOficial;

use App\Modules\Addons\MonitoreoOficial\Console\ImportDemoImpiCommand;
use App\Modules\Addons\MonitoreoOficial\Console\ReviewScheduledCasesCommand;
use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-monitoreo-oficial';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-monitoreo-oficial';

    public function register(): void
    {
        parent::register();

        // Config propia del módulo. `auto_seed_demo` = null a propósito:
        // la decisión real (sembrar o no el expediente de ejemplo de MEGANET)
        // se resuelve en ModuleDefinition::install() según el tipo de instancia,
        // NO aquí. El env solo permite FORZAR (true) o VETAR (false) ese default.
        $this->mergeConfigFrom(__DIR__ . '/config/monitoreo.php', 'monitoreo');
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportDemoImpiCommand::class,
                ReviewScheduledCasesCommand::class,
            ]);
        }
    }
}
