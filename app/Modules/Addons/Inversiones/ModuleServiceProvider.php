<?php

namespace App\Modules\Addons\Inversiones;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-inversiones';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-inversiones';
}
