<?php

namespace App\Modules\Addons\Talento;

use App\Modules\Addons\Talento\Console\CheckCredentialExpirationsCommand;
use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-talento';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-talento';

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckCredentialExpirationsCommand::class,
            ]);
        }
    }
}
