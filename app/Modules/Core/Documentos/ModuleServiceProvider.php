<?php

namespace App\Modules\Core\Documentos;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'core-documentos';
    protected string $moduleType = 'core';
    protected ?string $viewNamespace = 'core-documentos';
}
