<?php

namespace App\Modules\Addons\Hub;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-hub';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-hub';
}
