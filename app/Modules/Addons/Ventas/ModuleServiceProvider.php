<?php

namespace App\Modules\Addons\Ventas;

use App\Modules\Addons\Ventas\Console\LiberarCustodiasVencidasCommand;
use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-ventas';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-ventas';

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([
                LiberarCustodiasVencidasCommand::class,
            ]);
        }
    }
}
