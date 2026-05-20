<?php

namespace App\Modules\Core\Auth;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'core-auth';
    protected string $moduleType = 'core';
    protected ?string $viewNamespace = 'core-auth';
}
