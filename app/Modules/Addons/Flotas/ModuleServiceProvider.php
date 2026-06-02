<?php

namespace App\Modules\Addons\Flotas;

use App\Modules\Addons\Flotas\Console\SimulateGpsCommand;
use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-flotas';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-flotas';

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([
                SimulateGpsCommand::class,
            ]);
        }
    }
}
