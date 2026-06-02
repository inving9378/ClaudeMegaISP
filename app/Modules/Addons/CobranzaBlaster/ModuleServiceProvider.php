<?php

namespace App\Modules\Addons\CobranzaBlaster;

use App\Modules\Addons\CobranzaBlaster\Console\AmiEventListenerCommand;
use App\Modules\Addons\CobranzaBlaster\Services\AmiConnectionService;
use App\Modules\Addons\CobranzaBlaster\Services\CobranzaCampanaService;
use App\Modules\Addons\CobranzaBlaster\Services\CobranzaTtsService;
use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-cobranza-blaster';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-cobranza-blaster';

    public function register(): void
    {
        parent::register();

        $this->app->singleton(AmiConnectionService::class);
        $this->app->singleton(CobranzaTtsService::class);
        $this->app->singleton(CobranzaCampanaService::class);
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([
                AmiEventListenerCommand::class,
            ]);
        }
    }
}
