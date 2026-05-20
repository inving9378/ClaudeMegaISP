<?php

namespace App\Modules\Core\Localizacion;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'core-localizacion';
    protected string $moduleType = 'core';
    protected ?string $viewNamespace = 'core-localizacion';
}
