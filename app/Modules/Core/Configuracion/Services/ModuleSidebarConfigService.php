<?php

namespace App\Modules\Core\Configuracion\Services;

use App\Models\ModuleSidebarConfig;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ModuleSidebarConfigService
{
    /**
     * Parents cuyo partial (resources/views/module-sidebar/{key}.blade.php) tiene
     * un <ul class="sub-menu"> con el loop dynamic_children y por tanto SÍ pueden
     * hospedar hijos dinámicos (#132).
     *
     * Los demás módulos direct (mapas, olts, administracion, configuracion,
     * dashboard, marketing, warroom) son links planos sin sub-menú: un sub_item
     * asignado bajo ellos no se renderiza. Por eso el dropdown "Módulo padre" en
     * ModuleVisibilityConfig solo debe ofrecer los de esta lista.
     *
     * Mantener en sync si un partial gana o pierde su sub-menú.
     */
    public const SUBMENU_CAPABLE_PARENTS = [
        'planes', 'crm', 'clientes', 'gestion-red', 'finanzas', 'inventario',
        'cobranza-blaster', 'megafamilia', 'scheduling', 'embajadores', 'flotas',
        'talento', 'voip', 'devtools',
    ];

    /** ¿El módulo puede hospedar hijos dinámicos (tiene sub-menú en su partial)? */
    public function canHostChildren(string $moduleKey): bool
    {
        return in_array($moduleKey, self::SUBMENU_CAPABLE_PARENTS, true);
    }

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
