<?php

namespace App\Modules\Addons\Domiciliacion;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-domiciliacion';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-domiciliacion';
}
