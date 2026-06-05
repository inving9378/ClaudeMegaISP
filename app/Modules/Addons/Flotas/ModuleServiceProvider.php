<?php

namespace App\Modules\Addons\Flotas;

use App\Modules\Addons\Flotas\Console\CheckDocumentExpirationsCommand;
use App\Modules\Addons\Flotas\Console\CheckSubscriptionsCommand;
use App\Modules\Addons\Flotas\Console\GpsListenCommand;
use App\Modules\Addons\Flotas\Console\ReprocessGeofencesCommand;
use App\Modules\Addons\Flotas\Console\SimulateGpsCommand;
use App\Modules\Addons\Flotas\Console\SimulateRuptelaCommand;
use App\Modules\Addons\Flotas\Console\TestNotificationCommand;
use App\Modules\Addons\Flotas\Console\TestRuleCommand;
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
                CheckDocumentExpirationsCommand::class,
                CheckSubscriptionsCommand::class,
                GpsListenCommand::class,
                ReprocessGeofencesCommand::class,
                SimulateGpsCommand::class,
                SimulateRuptelaCommand::class,
                TestNotificationCommand::class,
                TestRuleCommand::class,
            ]);
        }
    }
}
