<?php

namespace App\Modules\Core\Layout\ViewComposers;

use App\Models\ModuleSidebarConfig;
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

        // Cache 60s — el sidebar renderiza en cada pageload.
        $sidebarItems = cache()->remember('sidebar_visible_items', 60, function () {
            return ModuleSidebarConfig::visibleInSidebar()
                ->orderBy('sidebar_section')
                ->orderBy('sidebar_position')
                ->get()
                ->groupBy('module_key');
        });

        $view->with('sidebarItems', $sidebarItems);
    }
}
