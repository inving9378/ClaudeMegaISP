<?php

namespace App\Modules\Addons\Planes;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-planes';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-planes';
}
