<?php

namespace App\Modules\Addons\Payments;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-payments';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-payments';
}
