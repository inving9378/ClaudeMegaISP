<?php

namespace App\Modules\Addons\Roadmap;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-roadmap';
    protected string $moduleType = 'addon';

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Modules\Addons\Roadmap\Console\RamaItemCommand::class,
                \App\Modules\Addons\Roadmap\Console\IntegrarItemCommand::class,
                \App\Modules\Addons\Roadmap\Console\FlagsCommand::class,
                \App\Modules\Addons\Roadmap\Console\RegistrarEjecucionCommand::class,
                \App\Modules\Addons\Roadmap\Console\VivoCommand::class,
                \App\Modules\Addons\Roadmap\Console\DisparoCheckCommand::class,
            ]);
        }
    }
}
