<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Item #505 — cierra el bypass de autorización de TestScriptController
 * (/script, /log-client, /information-dev, /data-client-mikrotik/*, /libera-ip/{id}):
 * antes solo llevaban 'auth', ahora exigen 'check_route_permission' + estos 3 permisos
 * dedicados (routes/web.php + config/route_permission.php).
 *
 * Decisión de Irving (brief item #505, opciones q1/q2): fix completo con permisos nuevos,
 * asignados SOLO a admin + soporte técnico. super-administrator/DESARROLLADOR ya bypasean
 * check_route_permission (isAdmin()) y 'Administrador' bypasea vía isSuperAdmin(); se
 * asignan igual (defensa en profundidad, mismo patrón que router_edit_router/ipv4_edit_ipv4
 * ya tienen). 'Super Administrador' y 'ADMINISTRADOR_COMPLETO' NO bypasean el middleware
 * (nombres de rol distintos a los que chequea isAdmin/isSuperAdmin) y sí lo necesitan.
 * 'TECNICO_PLANTA' = soporte técnico de red (ya tiene router_edit_router + ipv4_edit_ipv4
 * completos, es el rol técnico-de-alto-nivel real para operaciones de Mikrotik/IP en este
 * sistema). Aditivo/idempotente: givePermissionTo, NUNCA syncRoles/syncPermissions.
 */
return new class extends Migration
{
    // Solo /log-client: las otras rutas quedaron bajo sistema.test_script.manage (#9991145), decisión
    // posterior de Irving, limitada a super-administrator y DESARROLLADOR.
    private const PERMISOS = [
        'test.script.ejecutar',
    ];

    private const ROLES_ADICIONALES = [
        'Super Administrador',
        'Administrador',
        'ADMINISTRADOR_COMPLETO',
        'TECNICO_PLANTA',
    ];

    public function up(): void
    {
        foreach (self::PERMISOS as $nombre) {
            $perm = Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);

            // super-administrator + DESARROLLADOR (patrón estándar del proyecto)
            app(PermissionSyncService::class)->syncPermissionToBaseRoles($nombre);

            foreach (self::ROLES_ADICIONALES as $roleName) {
                $rol = Role::where('name', $roleName)->where('guard_name', 'web')->first();
                if ($rol && !$rol->hasPermissionTo($perm)) {
                    $rol->givePermissionTo($perm);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierte un cierre de bypass de autorización.
    }
};
