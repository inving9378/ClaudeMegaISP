<?php

namespace App\Modules\Core\Layout;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'core-layout';
    protected string $moduleType = 'core';
    protected ?string $viewNamespace = 'core-layout';
}
