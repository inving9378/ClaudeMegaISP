<?php

namespace App\Modules\Addons\Scheduling;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-scheduling';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-scheduling';
}
