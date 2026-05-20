<?php

namespace App\Modules\Core\Configuracion;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'core-configuracion';
    protected string $moduleType = 'core';
    protected ?string $viewNamespace = 'core-configuracion';
}
