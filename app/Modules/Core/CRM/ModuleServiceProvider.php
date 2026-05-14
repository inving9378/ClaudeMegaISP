<?php

namespace App\Modules\Core\CRM;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'core-crm';
    protected string $moduleType = 'core';
    protected ?string $viewNamespace = 'core-crm';
}
