<?php

namespace App\Modules\Addons\DevTools;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-devtools';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-devtools';
}
