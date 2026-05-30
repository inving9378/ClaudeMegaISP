<?php

namespace App\Modules\Addons\WarRoom;

use App\Modules\BaseModuleServiceProvider;
use App\Modules\Addons\WarRoom\Observers\ActionItemObserver;
use App\Modules\Addons\WarRoom\Models\ActionItem;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-warroom';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-warroom';

    public function boot(): void
    {
        parent::boot();

        ActionItem::observe(ActionItemObserver::class);
    }
}
