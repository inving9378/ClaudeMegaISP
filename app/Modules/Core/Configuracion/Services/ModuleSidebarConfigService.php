<?php

namespace App\Modules\Core\Configuracion\Services;

use App\Models\ModuleSidebarConfig;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ModuleSidebarConfigService
{
    public function get(string $moduleKey): ?ModuleSidebarConfig
    {
        return ModuleSidebarConfig::where('module_key', $moduleKey)->first();
    }

    public function save(string $moduleKey, array $data): ModuleSidebarConfig
    {
        $existing = $this->get($moduleKey);

        // Protección: módulos is_core no pueden ocultarse del sidebar.
        if ($existing && $existing->is_core && isset($data['show_in_sidebar']) && !$data['show_in_sidebar']) {
            throw ValidationException::withMessages([
                'show_in_sidebar' => "El módulo '{$moduleKey}' es core y no puede ocultarse del sidebar.",
            ]);
        }

        // is_core solo lo puede fijar el seeder, no save().
        unset($data['is_core']);

        $result = ModuleSidebarConfig::updateOrCreate(
            ['module_key' => $moduleKey],
            $data
        );

        cache()->forget('sidebar_visible_items');

        return $result;
    }

    public function listAll(): Collection
    {
        return ModuleSidebarConfig::orderBy('sidebar_section')
            ->orderBy('sidebar_position')
            ->orderBy('module_key')
            ->get();
    }

    public function listVisibleInSidebar(?string $section = null): Collection
    {
        $q = ModuleSidebarConfig::visibleInSidebar()
            ->orderBy('sidebar_position');

        if ($section !== null) {
            $q->where('sidebar_section', $section);
        }

        return $q->get();
    }

    public function listChildrenOf(string $parentKey): Collection
    {
        return ModuleSidebarConfig::where('sidebar_parent', $parentKey)
            ->where('sidebar_location', 'sub_item')
            ->orderBy('sidebar_position')
            ->get();
    }

    public function listInAdminSection(string $section): Collection
    {
        return ModuleSidebarConfig::where('show_in_sidebar', false)
            ->where('admin_section', $section)
            ->orderBy('sidebar_position')
            ->get();
    }

    public function listInConfigSection(): Collection
    {
        return ModuleSidebarConfig::where(function ($q) {
            $q->where('config_moved', true)
              ->orWhere('show_in_sidebar', false);
        })
        ->orderBy('configuracion_subsection')
        ->orderBy('sidebar_position')
        ->get();
    }
}
