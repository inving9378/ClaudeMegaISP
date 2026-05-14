<?php

namespace App\Modules\Addons\Vendedores;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-vendedores';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-vendedores';
}
