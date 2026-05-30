<?php

namespace App\Modules\Core\Layout\ViewComposers;

use App\Modules\Core\ModuleManager\Services\ModuleRegistry;
use Illuminate\View\View;

class SidebarComposer
{
    public function compose(View $view): void
    {
        try {
            $view->with('addonMenuItems', ModuleRegistry::instance()->getMenu());
        } catch (\Throwable) {
            $view->with('addonMenuItems', []);
        }
    }
}
