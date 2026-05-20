<?php

namespace App\Modules\Core\Documentacion;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'core-documentacion';
    protected string $moduleType = 'core';
    protected ?string $viewNamespace = 'core-documentacion';
}
