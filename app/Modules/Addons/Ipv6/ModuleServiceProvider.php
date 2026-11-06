<?php

namespace App\Modules\Addons\Ipv6;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-ipv6';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-ipv6';
}
