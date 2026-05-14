<?php

namespace App\Modules\Core\ModuleManager;

use App\Modules\BaseModuleServiceProvider;
use App\Modules\Core\ModuleManager\Console\ModuleListCommand;
use App\Modules\Core\ModuleManager\Services\ModuleManagerService;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'core-module-manager';
    protected string $moduleType = 'core';
    protected ?string $viewNamespace = 'core-module-manager';

    public function register(): void
    {
        parent::register();

        $this->app->singleton(ModuleManagerService::class, fn () => new ModuleManagerService());

        if ($this->app->runningInConsole()) {
            $this->commands([
                ModuleListCommand::class,
            ]);
        }
    }

    protected function moduleIsActive(): bool
    {
        // The Module Manager itself must always boot — otherwise no other module
        // can be evaluated. It is "core" in the strictest sense.
        return true;
    }
}
