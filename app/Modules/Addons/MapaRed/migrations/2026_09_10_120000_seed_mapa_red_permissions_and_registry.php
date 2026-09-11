<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Alta del addon MAPA DE RED en el registro de módulos + sus permisos Spatie.
 *
 * El módulo llevaba desplegado y COMPLETAMENTE INVISIBLE: `php artisan module:list`
 * lo reportaba `unregistered` aunque su código estuviera en el server (24 controladores,
 * 101 rutas, sus tablas `mapared_*` creadas y `/mapa-red` respondiendo). Faltaban dos
 * registros en la base que ninguna migración creaba:
 *
 *   1. La fila en `module_registry`. El sidebar renderiza los addons nuevos solo desde
 *      `ModuleRegistry::getMenu()`, que filtra por `activeSlugs()` — y esa lista sale de
 *      `module_registry`. Sin fila, `getMenu()` no lo devuelve y no se pinta nada.
 *   2. El permiso `mapa_red_view`. `config/route_permission.php` gatea `/mapa-red` y
 *      `/mapa-red/**` con él, y el sidebar filtra por `$authUser->can('mapa_red_view')`.
 *      No existía en `permissions`, y no hay `Gate::before`, así que no lo veía nadie —
 *      ni el super-administrator. (`permissions:sync-roles` no podía arreglarlo: reparte
 *      permisos existentes a los roles, no los crea.)
 *
 * Los nombres de permiso son EXACTAMENTE los declarados en el `module.json` del módulo:
 * `mapa_red_view` además debe coincidir carácter por carácter con la key de
 * `config/route_permission.php`, porque `CheckRoutePermission` compara
 * `isset($permissions[$key])` contra los permisos del usuario, no contra un slug de ruta.
 *
 * NO se toca `module_sidebar_config` a propósito: esa tabla alimenta los `@include`
 * explícitos del sidebar (`module-sidebar/<x>.blade.php`), y MAPA DE RED no tiene blade
 * propio — se pinta por la vía dinámica de `getMenu()`. Una fila ahí sería inerte hoy y
 * un duplicado el día que alguien le escriba el partial.
 *
 * Alcance conservador (patrón de addon-empresa / addon-centro-proyecto):
 * super-administrator y DESARROLLADOR. El módulo sale rotulado BETA en el sidebar;
 * ampliarlo a más roles es decisión de negocio para cuando termine la épica MR (#936).
 */
return new class extends Migration
{
    private const SLUG = 'addon-mapa-red';

    /** Los 7 permisos declarados en app/Modules/Addons/MapaRed/module.json. */
    private const PERMISOS = [
        'mapa_red_view'                => 'Ver módulo Mapa de Red',
        'mapa_red.cable.crear_rapido'  => 'Mapa de Red: alta rápida de cable/troncal',
        'mapa_red_historial_ver'       => 'Ver historial de cambios de nodos/enlaces del Mapa de Red',
        'mapa_red_trazar'              => 'Trazar enlaces en el Mapa de Red',
        'mapa_red_fotos_ver'           => 'Ver fotos de nodos/enlaces del Mapa de Red',
        'mapa_red_fotos_subir'         => 'Subir fotos a nodos/enlaces del Mapa de Red',
        'mapa_red_fotos_eliminar'      => 'Eliminar fotos de nodos/enlaces del Mapa de Red',
    ];

    private const ROLES = ['super-administrator', 'DESARROLLADOR'];

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permisos = [];
        foreach (array_keys(self::PERMISOS) as $nombre) {
            $permisos[] = Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
        }

        foreach (self::ROLES as $nombreRol) {
            $rol = Role::where('name', $nombreRol)->where('guard_name', 'web')->first();
            if (! $rol) {
                continue;   // entorno sin ese rol: no es motivo para abortar la migración
            }

            foreach ($permisos as $permiso) {
                if (! $rol->hasPermissionTo($permiso)) {
                    $rol->givePermissionTo($permiso);
                }
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // `installed_version` sigue al module.json (0.1.0). `module:lifecycle upgrade` la
        // moverá cuando la épica MR cierre y el módulo deje de ser beta.
        DB::table('module_registry')->updateOrInsert(
            ['slug' => self::SLUG],
            [
                'name'              => 'Mapa de Red',
                'installed_version' => '0.1.0',
                'type'              => 'addon',
                'active'            => true,
                'installed_at'      => now(),
                'updated_at'        => now(),
            ]
        );
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Solo los permisos que crea ESTA migración. `mapared.cobertura_declarada.manage`
        // es de 2026_09_08_010100 y se queda donde está.
        Permission::whereIn('name', array_keys(self::PERMISOS))
            ->where('guard_name', 'web')
            ->delete();

        DB::table('module_registry')->where('slug', self::SLUG)->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
