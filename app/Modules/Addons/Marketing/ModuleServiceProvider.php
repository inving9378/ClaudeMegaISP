<?php

namespace App\Modules\Addons\Marketing;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-marketing';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-marketing';

    public function register(): void
    {
        parent::register();

        $this->app->register(Providers\MarketingServiceProvider::class);
    }
}
