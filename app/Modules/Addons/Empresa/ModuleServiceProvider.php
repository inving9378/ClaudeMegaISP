<?php

namespace App\Modules\Addons\Empresa;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-empresa';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-empresa';
}
