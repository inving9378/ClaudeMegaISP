<?php

namespace App\Modules\Addons\Embajadores;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-embajadores';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-embajadores';
}
