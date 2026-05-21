<?php

namespace App\Modules\Addons\SmartImportExport;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-smart-import-export';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-smart-import-export';
}
