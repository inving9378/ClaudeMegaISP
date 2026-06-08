<?php

namespace App\Modules\Addons\WarRoom;

use App\Modules\Addons\WarRoom\Console\RefreshWarRoomCommand;
use App\Modules\Addons\WarRoom\Models\ActionItem;
use App\Modules\Addons\WarRoom\Observers\ActionItemObserver;
use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-warroom';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-warroom';

    public function boot(): void
    {
        parent::boot();

        ActionItem::observe(ActionItemObserver::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                RefreshWarRoomCommand::class,
            ]);
        }
    }
}
