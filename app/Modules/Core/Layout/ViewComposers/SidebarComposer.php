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
            $all = ModuleSidebarConfig::visibleInSidebar()
                ->orderBy('sidebar_section')
                ->orderBy('sidebar_position')
                ->get();

            // Hijos (sidebar_location='sub_item') agrupados por su módulo padre.
            $children = $all
                ->where('sidebar_location', 'sub_item')
                ->groupBy('sidebar_parent');

            // Top-level = SOLO 'direct', agrupado por module_key.
            // (groupBy se conserva porque el blade usa $sidebarItems['x']->first()).
            $topLevel = $all
                ->where('sidebar_location', 'direct')
                ->groupBy('module_key');

            // Adjunta a cada item top-level su colección de hijos dinámicos.
            $topLevel->each(function ($group) use ($children) {
                $group->each(function ($item) use ($children) {
                    $item->dynamic_children = $children->get($item->module_key, collect());
                });
            });

            return $topLevel;
        });

        $view->with('sidebarItems', $sidebarItems);
    }
}
