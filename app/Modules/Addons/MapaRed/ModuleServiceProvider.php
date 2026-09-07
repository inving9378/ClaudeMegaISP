<?php

namespace App\Modules\Addons\MapaRed;

use App\Modules\Addons\MapaRed\Console\BackfillCommand;
use App\Modules\Addons\MapaRed\Console\ValidarPresupuestoOpticoCommand;
use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-mapa-red';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-mapa-red';

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([
                BackfillCommand::class,
                ValidarPresupuestoOpticoCommand::class,
            ]);
        }
    }
}
