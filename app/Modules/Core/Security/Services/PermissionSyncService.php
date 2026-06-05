<?php

namespace App\Modules\Core\Security\Services;

use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Servicio centralizado de sincronización de permisos a roles base.
 *
 * Reglas de negocio (decisión Irving 2026-06-04):
 *  - super-administrator + DESARROLLADOR reciben TODOS los permisos.
 *  - Cualquier permiso que termine en .view se asigna también a TODOS los
 *    demás roles existentes (permiso "básico de lectura").
 *  - Todo método es idempotente: correrlo N veces no produce duplicados.
 */
class PermissionSyncService
{
    private const FULL_ACCESS_ROLES = ['super-administrator', 'DESARROLLADOR'];

    /**
     * Sincroniza UN permiso recién creado a los roles base.
     * Llamado por ModuleLifecycleService::registerPermissions() en cada install/upgrade.
     */
    public function syncPermissionToBaseRoles(string $permissionName): void
    {
        $perm = Permission::where('name', $permissionName)->where('guard_name', 'web')->first();
        if (!$perm) {
            return;
        }

        $this->giveToFullAccessRoles($perm);

        if ($this->isViewPermission($permissionName)) {
            $this->giveToAllOtherRoles($perm);
        }

        $this->resetCache();
    }

    /**
     * Sincroniza TODOS los permisos existentes en BD a los roles base.
     * Idempotente. Devuelve conteo de asignaciones nuevas por categoría.
     *
     * @return array{super-administrator: int, DESARROLLADOR: int, others_view: int}
     */
    public function syncAllPermissionsToBaseRoles(): array
    {
        $report = ['super-administrator' => 0, 'DESARROLLADOR' => 0, 'others_view' => 0];

        $allPerms = Permission::where('guard_name', 'web')->get();

        foreach ($allPerms as $perm) {
            foreach (self::FULL_ACCESS_ROLES as $roleName) {
                $role = Role::where('name', $roleName)->first();
                if ($role && !$role->hasPermissionTo($perm)) {
                    $role->givePermissionTo($perm);
                    $report[$roleName]++;
                }
            }

            if ($this->isViewPermission($perm->name)) {
                $report['others_view'] += $this->giveToAllOtherRoles($perm);
            }
        }

        $this->resetCache();

        Log::info('PermissionSyncService: sync completo', $report);

        return $report;
    }

    /**
     * Lee los module.json de todos los addons, crea permisos faltantes en BD
     * y los sincroniza a los roles base.
     *
     * @return array{created: string[], synced: int}
     */
    public function syncFromModuleManifests(): array
    {
        $created = [];
        $synced  = 0;

        $manifests = glob(base_path('app/Modules/Addons/*/module.json'));

        foreach ($manifests as $manifestPath) {
            $manifest    = json_decode(file_get_contents($manifestPath), true);
            $permissions = $manifest['permissions'] ?? [];

            foreach ($permissions as $permDef) {
                $name = is_string($permDef) ? $permDef : ($permDef['name'] ?? null);
                if (!$name) {
                    continue;
                }

                $existed = Permission::where('name', $name)->where('guard_name', 'web')->exists();
                if (!$existed) {
                    Permission::create([
                        'name'        => $name,
                        'guard_name'  => 'web',
                        'description' => is_array($permDef) ? ($permDef['description'] ?? '') : '',
                    ]);
                    $created[] = $name;
                }

                $this->syncPermissionToBaseRoles($name);
                $synced++;
            }
        }

        $this->resetCache();

        return ['created' => $created, 'synced' => $synced];
    }

    // ── Helpers privados ───────────────────────────────────────────────────────

    private function isViewPermission(string $name): bool
    {
        return str_ends_with($name, '.view');
    }

    /** Asigna el permiso a super-administrator y DESARROLLADOR. */
    private function giveToFullAccessRoles(Permission $perm): void
    {
        foreach (self::FULL_ACCESS_ROLES as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && !$role->hasPermissionTo($perm)) {
                $role->givePermissionTo($perm);
            }
        }
    }

    /**
     * Asigna el permiso a todos los roles que NO son full-access.
     * @return int número de asignaciones nuevas
     */
    private function giveToAllOtherRoles(Permission $perm): int
    {
        $count = 0;
        $others = Role::whereNotIn('name', self::FULL_ACCESS_ROLES)->get();
        foreach ($others as $role) {
            if (!$role->hasPermissionTo($perm)) {
                $role->givePermissionTo($perm);
                $count++;
            }
        }
        return $count;
    }

    private function resetCache(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
