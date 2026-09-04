<?php

namespace App\Modules\Core\Security\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Servicio centralizado de sincronización de permisos a roles base.
 *
 * Reglas de negocio:
 *  - super-administrator + DESARROLLADOR reciben TODOS los permisos (siempre).
 *  - El reparto automático de .view a los demás roles base está GATEADO por
 *    config('permission_sync.auto_grant_view_base_roles') (default false, item #309).
 *    Con el flag en false los roles base NO reciben .view automáticamente; en true
 *    se restaura el comportamiento previo (decisión 2026-06-04).
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

        if ($this->autoGrantViewToBaseRoles() && $this->isViewPermission($permissionName)) {
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

            if ($this->autoGrantViewToBaseRoles() && $this->isViewPermission($perm->name)) {
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

        $manifests = array_merge(
            glob(base_path('app/Modules/Addons/*/module.json')) ?: [],
            glob(base_path('app/Modules/Core/*/module.json')) ?: []
        );

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
                $this->syncScopeDeclaration($name, is_array($permDef) ? ($permDef['scope_propios'] ?? null) : null);
                $synced++;
            }
        }

        $this->resetCache();

        return ['created' => $created, 'synced' => $synced];
    }

    /**
     * Item #851 (Fase A) — declara (o actualiza) el criterio de "alcance propio" de un
     * permiso, leído de la clave opcional `scope_propios` en module.json. Puramente
     * declarativo: NO filtra nada (eso lo hace el Global Scope de una fase posterior).
     * Permisos sin esa clave no tocan `permission_scopes` — siguen viéndose "todos" igual
     * que hoy. Idempotente (upsert por permission_id).
     */
    public function syncScopeDeclaration(string $permissionName, ?string $criterioPropios): void
    {
        if (!$criterioPropios || !Schema::hasTable('permission_scopes')) {
            return;
        }

        $perm = Permission::where('name', $permissionName)->where('guard_name', 'web')->first();
        if (!$perm) {
            return;
        }

        $existing = DB::table('permission_scopes')->where('permission_id', $perm->id)->first();
        if ($existing) {
            DB::table('permission_scopes')->where('permission_id', $perm->id)
                ->update(['criterio_propios' => $criterioPropios, 'updated_at' => now()]);
        } else {
            DB::table('permission_scopes')->insert([
                'permission_id'    => $perm->id,
                'criterio_propios' => $criterioPropios,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
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
     * Asigna el permiso a todos los roles que NO son full-access ni están en la lista de exclusión.
     * La lista de exclusión se configura en config/permission_sync.php (view_excluded_roles).
     * @return int número de asignaciones nuevas
     */
    private function giveToAllOtherRoles(Permission $perm): int
    {
        $count = 0;
        $skip  = array_merge(self::FULL_ACCESS_ROLES, $this->getViewExcludedRoles());
        $others = Role::whereNotIn('name', $skip)->get();
        foreach ($others as $role) {
            if (!$role->hasPermissionTo($perm)) {
                $role->givePermissionTo($perm);
                $count++;
            }
        }
        return $count;
    }

    /** Lee la lista de roles excluidos del reparto automático de .view desde config. */
    private function getViewExcludedRoles(): array
    {
        return config('permission_sync.view_excluded_roles', []);
    }

    /**
     * ¿Debe auto-otorgarse .view a los roles base? (item #309)
     * Default false: el auto-grant re-ensanchaba los roles base podados por la reforma.
     * super-administrator/DESARROLLADOR NO dependen de este flag (reciben todo siempre).
     */
    private function autoGrantViewToBaseRoles(): bool
    {
        return (bool) config('permission_sync.auto_grant_view_base_roles', false);
    }

    private function resetCache(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
