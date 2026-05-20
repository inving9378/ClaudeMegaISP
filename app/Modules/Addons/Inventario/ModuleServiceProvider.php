<?php

namespace App\Modules\Addons\Inventario;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-inventario';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-inventario';
}
