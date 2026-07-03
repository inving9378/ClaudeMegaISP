<?php

namespace App\Modules\Core\ModuleManager\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ModuleSidebarConfig;
use App\Modules\Core\ModuleManager\Services\ModuleRegistry;
use Illuminate\Http\JsonResponse;

class AdminPanelController extends Controller
{
    public function index()
    {
        return view('core-module-manager::admin-panel');
    }

    /**
     * Tarjetas del panel de Administración.
     *
     * Combina dos fuentes (item #130):
     *  A) admin_cards declaradas en cada module.json (mecanismo existente).
     *  B) tiles SINTÉTICOS para módulos ocultos del sidebar (show_in_sidebar=false)
     *     que quedarían huérfanos por no declarar admin_card.
     *
     * Ambas fuentes se filtran SERVER-SIDE por el permiso .view del módulo: un
     * usuario no recibe tarjetas de módulos a los que no tiene acceso. Las cards
     * SIN campo `permission` se muestran tal cual (no se ocultan por accidente).
     */
    public function cards(): JsonResponse
    {
        $registry = ModuleRegistry::instance();
        $user     = auth()->user();

        $can = fn (?string $permission): bool =>
            empty($permission) || ($user && $user->can($permission));

        $allCards = $registry->getAdminCards();

        // A) admin_cards existentes, ahora filtradas por permiso (cierra el gap
        //    de seguridad: antes se devolvían TODAS a TODOS).
        $cards = array_values(array_filter(
            $allCards,
            fn ($card) => $can($card['permission'] ?? null)
        ));

        // Slugs (pelados de prefijo core-/addon-) que YA aportan admin_card →
        // base del DEDUPE: si un módulo ya es tile por mecanismo A, no se sintetiza.
        $strip = fn (?string $slug): string => preg_replace('/^(core|addon)-/', '', $slug ?? '');
        $cardKeys = array_map(fn ($c) => $strip($c['_module'] ?? ''), $allCards);

        // Índice de la entrada de menú PRIMARIA por módulo activo (solo módulos
        // activos aparecen en getMenu → cumple criterio "módulo activo"). De aquí
        // sale la URL/permiso/icono porque module_sidebar_config.sidebar_url es NULL.
        $menuByKey = [];
        foreach ($registry->getMenu() as $item) {
            $key = $strip($item['_module'] ?? '');
            if ($key !== '' && ! isset($menuByKey[$key])) {
                $menuByKey[$key] = $item;
            }
        }

        // B) tiles sintéticos para módulos ocultos.
        foreach (ModuleSidebarConfig::where('show_in_sidebar', false)->get() as $cfg) {
            $key = $cfg->module_key;

            if (in_array($key, $cardKeys, true)) {
                continue; // dedupe: ya tiene admin_card propia.
            }

            $menu = $menuByKey[$key] ?? null;
            if (! $menu) {
                continue; // módulo inactivo o sin menú → nada que enlazar.
            }

            $url = $menu['url'] ?? $cfg->sidebar_url ?? null;
            if (empty($url)) {
                continue; // sin URL navegable → no se sintetiza.
            }

            $permission = $menu['permission'] ?? null;
            if (! $can($permission)) {
                continue; // el usuario no tiene el .view del módulo.
            }

            // El front renderiza `fa fa-fw fa-{icon}`, así que el icono va SIN el
            // prefijo `fa-` (las admin_cards usan p.ej. "truck"); sidebar_icon a
            // veces lo trae ("fa-plug") → se normaliza quitándolo para no duplicar.
            $icon = preg_replace('/^fa-/', '', $cfg->sidebar_icon ?: ($menu['icon'] ?? 'cube'));

            $cards[] = [
                'title'       => $cfg->sidebar_label ?: ($menu['label'] ?? $key),
                'description' => 'Módulo oculto del menú lateral — acceso rápido.',
                'icon'        => $icon ?: 'cube',
                'url'         => $url,
                'permission'  => $permission,
                '_module'     => $menu['_module'] ?? ('addon-' . $key),
                '_synthetic'  => true,
            ];
        }

        return response()->json([
            'cards' => array_values($cards),
        ]);
    }

    public function configSections(): JsonResponse
    {
        $registry = ModuleRegistry::instance();
        $user     = auth()->user();

        // Una sección puede declarar `role` para gating estricto por ROL (no
        // permiso, que se sincroniza a roles base). Sin `role` → visible como
        // siempre (aditivo, sin regresión para otros módulos).
        $roleOk = fn (array $section): bool =>
            empty($section['role']) || ($user && $user->hasRole($section['role']));

        $flat = array_values(array_filter($registry->getConfigSectionsFlat(), $roleOk));

        // Re-agrupar por type respetando el filtro (mismo shape que el registry).
        $grouped = [];
        foreach ($flat as $section) {
            $grouped[$section['type'] ?? 'general'][] = $section;
        }

        return response()->json([
            'sections'      => $grouped,
            'sections_flat' => $flat,
        ]);
    }
}
