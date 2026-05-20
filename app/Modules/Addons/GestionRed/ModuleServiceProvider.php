<?php

namespace App\Modules\Addons\GestionRed;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-gestion-red';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-gestion-red';
}
