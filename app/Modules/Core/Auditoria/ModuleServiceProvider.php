<?php

namespace App\Modules\Core\Auditoria;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'core-auditoria';
    protected string $moduleType = 'core';
    protected ?string $viewNamespace = 'core-auditoria';
}
