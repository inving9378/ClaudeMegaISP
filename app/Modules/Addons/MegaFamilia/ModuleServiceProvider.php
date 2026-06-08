<?php

namespace App\Modules\Addons\MegaFamilia;

use App\Modules\Addons\MegaFamilia\Console\DeployApkCommand;
use App\Modules\Addons\MegaFamilia\Services\ApkDeployService;
use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-megafamilia';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-megafamilia';

    public function register(): void
    {
        parent::register();
        $this->app->singleton(ApkDeployService::class);
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([DeployApkCommand::class]);
        }
    }
}
