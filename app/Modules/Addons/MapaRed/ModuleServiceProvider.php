<?php

namespace App\Modules\Addons\MapaRed;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-mapa-red';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-mapa-red';
}
