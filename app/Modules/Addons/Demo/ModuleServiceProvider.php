<?php

namespace App\Modules\Addons\Demo;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-demo';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-demo';
}
