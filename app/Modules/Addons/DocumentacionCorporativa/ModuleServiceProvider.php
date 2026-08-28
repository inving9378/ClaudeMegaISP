<?php

namespace App\Modules\Addons\DocumentacionCorporativa;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-documentacion-corporativa';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-documentacion-corporativa';
}
