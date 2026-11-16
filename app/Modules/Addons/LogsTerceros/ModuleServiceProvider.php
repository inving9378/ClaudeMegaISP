<?php

namespace App\Modules\Addons\LogsTerceros;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-logs-terceros';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-logs-terceros';
}
