<?php

namespace App\Modules\Core\Security\Support;

/**
 * Convención de nombres para el árbol jerárquico de permisos (item #537, Fase 1).
 *
 * Decisión de Irving (q1): dot-notation SOLO para permisos NUEVOS de jerarquía
 * módulo → sección/tarjeta → acción. Los ~359 permisos legacy snake_case
 * (dashboard_view_dashboard, etc.) NO se tocan y conviven indefinidamente con
 * esta convención (ya es el estado de facto: fleet.* y talento.* conviven hoy
 * con dashboard_view_*).
 *
 * Fase 2 (piloto Dashboard) usa estos builders al declarar los permisos nuevos
 * en su module.json; ModuleLifecycleService::registerPermissions() no necesita
 * cambios — ya trata cada entrada de forma genérica (name/description).
 */
class PermissionNaming
{
    public const TYPE_VIEW    = 'view';
    public const TYPE_SECTION = 'section';
    public const TYPE_CARD    = 'card';
    public const TYPE_ACTION  = 'action';

    private const SLUG_RE = '[a-z][a-z0-9_]*';

    /** Permiso "Ver módulo" — controla si el módulo aparece en el sidebar. */
    public static function view(string $module): string
    {
        return "{$module}.view";
    }

    /** Permiso "Ver sección/pantalla" dentro de un módulo. */
    public static function section(string $module, string $slug): string
    {
        return "{$module}.section.{$slug}.view";
    }

    /** Permiso "Ver bloque/tarjeta" dentro de un módulo. */
    public static function card(string $module, string $slug): string
    {
        return "{$module}.card.{$slug}.view";
    }

    /** Permiso de acción (add/edit/remove/específica) dentro de un módulo. */
    public static function action(string $module, string $slug): string
    {
        return "{$module}.action.{$slug}";
    }

    /** ¿El nombre sigue la convención jerárquica dot-notation nueva? */
    public static function isHierarchical(string $name): bool
    {
        return self::parse($name) !== null;
    }

    /**
     * Descompone un nombre jerárquico en sus partes.
     *
     * @return array{module: string, type: string, slug: ?string}|null null si no coincide con la convención.
     */
    public static function parse(string $name): ?array
    {
        $slug = self::SLUG_RE;

        if (preg_match('/^(' . $slug . ')\.view$/', $name, $m)) {
            return ['module' => $m[1], 'type' => self::TYPE_VIEW, 'slug' => null];
        }
        if (preg_match('/^(' . $slug . ')\.section\.(' . $slug . ')\.view$/', $name, $m)) {
            return ['module' => $m[1], 'type' => self::TYPE_SECTION, 'slug' => $m[2]];
        }
        if (preg_match('/^(' . $slug . ')\.card\.(' . $slug . ')\.view$/', $name, $m)) {
            return ['module' => $m[1], 'type' => self::TYPE_CARD, 'slug' => $m[2]];
        }
        if (preg_match('/^(' . $slug . ')\.action\.(' . $slug . ')$/', $name, $m)) {
            return ['module' => $m[1], 'type' => self::TYPE_ACTION, 'slug' => $m[2]];
        }

        return null;
    }
}
