<?php

namespace App\Modules\Addons\IA;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-ia';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-ia';
}
