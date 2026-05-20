<?php

namespace App\Modules\Core\Usuarios;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'core-usuarios';
    protected string $moduleType = 'core';
    protected ?string $viewNamespace = 'core-usuarios';
}
