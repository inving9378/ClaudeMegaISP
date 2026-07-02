<?php

namespace App\Modules\Addons\Payments;

use App\Modules\Addons\Payments\Console\ApplyIdentifiedPaymentsCommand;
use App\Modules\Addons\Payments\Console\ConciliacionDemoCommand;
use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-payments';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-payments';

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ApplyIdentifiedPaymentsCommand::class,
                ConciliacionDemoCommand::class,
            ]);
        }
    }
}
