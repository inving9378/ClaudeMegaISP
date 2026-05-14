<?php

namespace App\Modules\Core\Clientes;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'core-clientes';
    protected string $moduleType = 'core';
    protected ?string $viewNamespace = 'core-clientes';
}
