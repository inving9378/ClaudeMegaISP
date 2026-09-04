<?php

namespace App\Modules\Core\Notifications;

use App\Modules\BaseModuleServiceProvider;
use App\Modules\Core\Notifications\Console\PurgePushTokensCommand;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'core-notifications';
    protected string $moduleType = 'core';
    protected ?string $viewNamespace = 'core-notifications';

    public function boot(): void
    {
        parent::boot();

        if ($this->moduleIsActive() && $this->app->runningInConsole()) {
            $this->commands([
                PurgePushTokensCommand::class,
            ]);
        }
    }
}
