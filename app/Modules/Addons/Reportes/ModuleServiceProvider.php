<?php

namespace App\Modules\Addons\Reportes;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-reportes';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-reportes';
}
