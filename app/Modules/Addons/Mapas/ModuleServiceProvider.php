<?php

namespace App\Modules\Addons\Mapas;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-mapas';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-mapas';
}
