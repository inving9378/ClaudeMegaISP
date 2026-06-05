<?php

namespace App\Modules\Core\ModuleManager;

use App\Modules\BaseModuleServiceProvider;
use App\Modules\Core\ModuleManager\Console\ModuleLifecycleCommand;
use App\Modules\Core\Security\Console\SyncPermissionsCommand;
use App\Modules\Core\ModuleManager\Console\ModuleListCommand;
use App\Modules\Core\ModuleManager\Services\ModuleLifecycleService;
use App\Modules\Core\ModuleManager\Services\ModuleManagerService;
use App\Modules\Core\ModuleManager\Services\ModuleRegistry;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'core-module-manager';
    protected string $moduleType = 'core';
    protected ?string $viewNamespace = 'core-module-manager';

    public function register(): void
    {
        parent::register();

        $this->app->singleton(ModuleManagerService::class, fn () => new ModuleManagerService());
        $this->app->singleton(ModuleRegistry::class, fn () => ModuleRegistry::instance());
        $this->app->singleton(ModuleLifecycleService::class, fn () => new ModuleLifecycleService());

        if ($this->app->runningInConsole()) {
            $this->commands([
                ModuleListCommand::class,
                ModuleLifecycleCommand::class,
                SyncPermissionsCommand::class,
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
