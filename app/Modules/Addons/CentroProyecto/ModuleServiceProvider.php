<?php

namespace App\Modules\Addons\CentroProyecto;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-centro-proyecto';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-centro-proyecto';
}
