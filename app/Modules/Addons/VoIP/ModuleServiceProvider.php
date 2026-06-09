<?php

namespace App\Modules\Addons\VoIP;

use App\Modules\Addons\VoIP\Services\AsteriskProvisioningService;
use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-voip';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-voip';

    public function register(): void
    {
        parent::register();

        $this->app->singleton(AsteriskProvisioningService::class);
    }
}
