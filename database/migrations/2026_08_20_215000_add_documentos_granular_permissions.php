<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Item #839 — scaffolding de permisos granulares para core-documentos.
 *
 * core-documentos (plantillas de documentos y sus tipos, `app/Modules/Core/Documentos`)
 * opera hoy bajo el permiso monolítico legado `config_view_system`, compartido con
 * otras secciones no relacionadas de Configuración → Sistema (additional-fields,
 * template-task, list-template-verification...). Cualquiera que vea esa sección
 * puede crear/editar/borrar plantillas de documentos, sin granularidad entre
 * ver/crear/editar/borrar.
 *
 * Decisión aprobada por Irving (opción dual/compatibilidad, 2026-08-20, item #839):
 *  - Se crean 4 permisos granulares nuevos (documentos.view/create/edit/delete).
 *  - `config_view_system` NO se retira aquí (fallback OR — ver config/route_permission.php,
 *    donde los mismos paths de document_template/document_type_template quedan
 *    registrados bajo AMBAS llaves). Retirar el legado queda para un item posterior,
 *    una vez verificado en dev.
 *  - Preservar acceso 1:1: todo rol que hoy tenga `config_view_system` recibe también
 *    los 4 permisos nuevos (no se quita ni se amplía acceso real, solo se prepara la
 *    granularidad para cuando se retire el legado).
 *  - Rollback: down() revierte exactamente las asignaciones que up() creó. No se
 *    necesita snapshot SQL aparte — el blast radius son 4 permisos nuevos y los
 *    roles que ya tenían el legado (5 roles admin en dev, verificado en Paso 0).
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'documentos.view'   => 'Ver plantillas de documentos y sus tipos',
        'documentos.create' => 'Crear plantillas de documentos y tipos',
        'documentos.edit'   => 'Editar plantillas de documentos y tipos',
        'documentos.delete' => 'Eliminar plantillas de documentos y tipos',
    ];

    public function up(): void
    {
        $syncService = app(PermissionSyncService::class);

        $legacyRoleIds = Role::whereHas('permissions', function ($q) {
            $q->where('name', 'config_view_system')->where('guard_name', 'web');
        })->pluck('id');

        $pivotRows = [];
        foreach (self::PERMISSIONS as $name => $description) {
            $permission = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );

            // super-administrator + DESARROLLADOR (política central del servicio).
            $syncService->syncPermissionToBaseRoles($name);

            // Preservar acceso 1:1: quien hoy ve la sección vía el permiso legado
            // también recibe el granular nuevo. insertOrIgnore evita duplicados sin
            // depender de la caché de relaciones de Eloquent/Spatie entre iteraciones
            // (un Role reusado entre permisos puede quedar con una copia stale de
            // `permissions` y disparar un insert duplicado vía givePermissionTo).
            foreach ($legacyRoleIds as $roleId) {
                $pivotRows[] = ['role_id' => $roleId, 'permission_id' => $permission->id];
            }
        }

        if (! empty($pivotRows)) {
            DB::table('role_has_permissions')->insertOrIgnore($pivotRows);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $names = array_keys(self::PERMISSIONS);
        $perms = Permission::whereIn('name', $names)->where('guard_name', 'web')->get();

        foreach ($perms as $perm) {
            DB::table('model_has_permissions')->where('permission_id', $perm->id)->delete();
            DB::table('role_has_permissions')->where('permission_id', $perm->id)->delete();
            $perm->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
