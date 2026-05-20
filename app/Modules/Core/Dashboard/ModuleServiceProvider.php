<?php

namespace App\Modules\Core\Dashboard;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'core-dashboard';
    protected string $moduleType = 'core';
    protected ?string $viewNamespace = 'core-dashboard';
}
