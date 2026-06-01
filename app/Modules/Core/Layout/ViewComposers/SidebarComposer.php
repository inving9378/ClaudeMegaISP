<?php

namespace App\Modules\Core\Layout\ViewComposers;

use App\Modules\Core\ModuleManager\Services\ModuleRegistry;
use Illuminate\View\View;

class SidebarComposer
{
    public function compose(View $view): void
    {
        try {
            $registry = ModuleRegistry::instance();
            $view->with('addonMenuItems',   $registry->getMenu());
            $view->with('sidebarSubmenu',   $registry->getSubmenuItemsFor('finanzas'));
        } catch (\Throwable) {
            $view->with('addonMenuItems', []);
            $view->with('sidebarSubmenu', []);
        }
    }
}
