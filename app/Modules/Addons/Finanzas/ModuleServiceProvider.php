<?php

namespace App\Modules\Addons\Finanzas;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-finanzas';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-finanzas';
}
