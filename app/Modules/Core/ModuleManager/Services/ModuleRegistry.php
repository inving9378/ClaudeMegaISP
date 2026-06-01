<?php

namespace App\Modules\Core\ModuleManager\Services;

use App\Modules\Core\ModuleManager\Models\ModuleRegistry as ModuleRegistryModel;
use Illuminate\Support\Facades\Schema;

/**
 * Compiladora de áreas declarativas.
 *
 * Lee los manifiestos de módulos activos y devuelve colecciones listas para
 * el frontend: menú operativo, tarjetas Admin, secciones Config (agrupadas
 * por type), endpoints API y contexto IA.
 *
 * Singleton por request; se invalida llamando clearCache().
 */
class ModuleRegistry
{
    private static ?self $instance = null;

    private ?array $compiled = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public static function clearCache(): void
    {
        self::$instance = null;
    }

    // ── API pública ──────────────────────────────────────────────────────────

    /** Items del menú lateral (solo módulos activos, en orden de aparición). */
    public function getMenu(): array
    {
        return $this->compiled()['menu'];
    }

    /** Tarjetas para el panel central de Administración. */
    public function getAdminCards(): array
    {
        return $this->compiled()['admin_cards'];
    }

    /**
     * Secciones de Configuración agrupadas por type.
     * Estructura: ['api_key' => [...sections], 'notificaciones' => [...], ...]
     */
    public function getConfigSections(): array
    {
        return $this->compiled()['config_sections'];
    }

    /**
     * Secciones de Configuración como lista plana (sin agrupar).
     */
    public function getConfigSectionsFlat(): array
    {
        return $this->compiled()['config_sections_flat'];
    }

    /** Endpoints API de todos los módulos activos. */
    public function getApiEndpoints(): array
    {
        return $this->compiled()['api_endpoints'];
    }

    /** Contexto IA (knowledge + acciones) de todos los módulos activos. */
    public function getAiContext(): array
    {
        return $this->compiled()['ai_context'];
    }

    /** Pestañas de ficha de cliente listas para renderizar (excluye deferred). */
    public function getClientTabs(): array
    {
        return $this->compiled()['client_tabs'];
    }

    /**
     * Pestañas diferidas: módulos que declaran client_tab con deferred:true.
     * Permite auditar la deuda pendiente de infra sin bloquear el render.
     */
    public function getClientTabsDeferred(): array
    {
        return $this->compiled()['client_tabs_deferred'];
    }

    /** Tipos de servicio contratable declarados por módulos activos. */
    public function getServiceTypes(): array
    {
        return $this->compiled()['service_types'];
    }

    /**
     * Children de menú de los módulos con sidebar.location=submenu y
     * sidebar.parent=$parent. Usado para inyectar secciones (ej. Marketing
     * dentro de Finanzas) sin hardcodear la jerarquía en el Blade.
     *
     * Retorna array plano de items: cada uno tiene label, url, permission,
     * icon y _module.
     */
    public function getSubmenuItemsFor(string $parent): array
    {
        return $this->compiled()['sidebar_submenu'][$parent] ?? [];
    }

    /**
     * Ayuda contextual por pantalla de todos los módulos activos (lista plana).
     * Filtra por URL si se provee.
     */
    public function getScreenHelp(?string $url = null): array
    {
        $screens = $this->compiled()['screens'];
        if ($url === null) {
            return $screens;
        }
        return array_values(array_filter($screens, fn ($s) => ($s['url'] ?? '') === $url));
    }

    // ── Compilación ──────────────────────────────────────────────────────────

    private function compiled(): array
    {
        if ($this->compiled !== null) {
            return $this->compiled;
        }

        $activeSlugs = $this->activeSlugs();
        $manifests   = app(ModuleManagerService::class)->manifests();

        $menu              = [];
        $adminCards        = [];
        $configSectionsRaw = [];
        $apiEndpoints      = [];
        $aiContext         = [];
        $clientTabs        = [];
        $clientTabsDeferred = [];
        $serviceTypes      = [];
        $screens           = [];
        $sidebarSubmenu    = [];

        foreach ($manifests as $manifest) {
            $slug = $manifest['slug'] ?? null;
            if ($slug === null || ! in_array($slug, $activeSlugs, true)) {
                continue;
            }

            // sidebar.location=submenu: agrupa children por parent slug
            $sidebarDecl = $manifest['sidebar'] ?? [];
            if (($sidebarDecl['location'] ?? '') === 'submenu' && ! empty($sidebarDecl['parent'])) {
                $parentKey = $sidebarDecl['parent'];
                foreach ($manifest['menu'] ?? [] as $item) {
                    foreach ($item['children'] ?? [] as $child) {
                        $sidebarSubmenu[$parentKey][] = array_merge($child, ['_module' => $slug]);
                    }
                }
            }

            foreach ($manifest['menu'] ?? [] as $item) {
                $menu[] = array_merge($item, ['_module' => $slug]);
            }

            foreach ($manifest['admin_cards'] ?? [] as $card) {
                $adminCards[] = array_merge($card, ['_module' => $slug]);
            }

            foreach ($manifest['config_sections'] ?? [] as $section) {
                $configSectionsRaw[] = array_merge($section, ['_module' => $slug]);
            }

            foreach ($manifest['api_endpoints'] ?? [] as $endpoint) {
                $apiEndpoints[] = array_merge($endpoint, ['_module' => $slug]);
            }

            $ai = $manifest['ai'] ?? [];
            if (! empty($ai)) {
                $aiContext[] = array_merge($ai, ['_module' => $slug]);
            }

            $clientTab = $manifest['client_tab'] ?? null;
            if (! empty($clientTab)) {
                if ($clientTab['deferred'] ?? false) {
                    $clientTabsDeferred[] = array_merge($clientTab, ['_module' => $slug]);
                } else {
                    $clientTabs[] = array_merge($clientTab, ['_module' => $slug]);
                }
            }

            foreach ($manifest['screens'] ?? [] as $screen) {
                $screens[] = array_merge($screen, ['_module' => $slug]);
            }

            if (! empty($manifest['service_type'])) {
                $serviceTypes[] = array_merge($manifest['service_type'], ['_module' => $slug]);
            }
        }

        // Agrupar secciones de config por type
        $configSections = [];
        foreach ($configSectionsRaw as $section) {
            $type = $section['type'] ?? 'general';
            $configSections[$type][] = $section;
        }

        $this->compiled = [
            'menu'                 => $menu,
            'admin_cards'          => $adminCards,
            'config_sections'      => $configSections,
            'config_sections_flat' => $configSectionsRaw,
            'api_endpoints'        => $apiEndpoints,
            'ai_context'           => $aiContext,
            'client_tabs'          => $clientTabs,
            'client_tabs_deferred' => $clientTabsDeferred,
            'service_types'        => $serviceTypes,
            'screens'              => $screens,
            'sidebar_submenu'      => $sidebarSubmenu,
        ];

        return $this->compiled;
    }

    private function activeSlugs(): array
    {
        try {
            if (! Schema::hasTable('module_registry')) {
                return [];
            }
            return ModuleRegistryModel::where('active', true)
                ->pluck('slug')
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }
}
