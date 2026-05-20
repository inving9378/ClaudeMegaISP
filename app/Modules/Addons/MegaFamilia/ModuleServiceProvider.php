<?php

namespace App\Modules\Addons\MegaFamilia;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-megafamilia';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-megafamilia';
}
