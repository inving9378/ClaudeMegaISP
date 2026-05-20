<?php

namespace App\Modules\Core\Release;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'core-release';
    protected string $moduleType = 'core';
    protected ?string $viewNamespace = 'core-release';
}
