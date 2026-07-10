<?php

namespace App\Modules\Core\Usuarios\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Promotion;
use App\Models\User;
use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{

    public function __construct() {}

    /**
     * POST /administracion/permisos/sync-roles
     * Solo super-administrator. Sincroniza todos los permisos faltantes a roles base.
     */
    public function syncRoles(PermissionSyncService $sync): JsonResponse
    {
        if (!auth()->user()?->hasRole('super-administrator')) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        $report = $sync->syncAllPermissionsToBaseRoles();

        return response()->json([
            'ok'      => true,
            'report'  => $report,
            'total'   => array_sum($report),
            'message' => array_sum($report) === 0
                ? 'Todo ya estaba sincronizado. No se requirieron cambios.'
                : 'Sincronización completada: ' . array_sum($report) . ' permisos asignados.',
        ]);
    }

    public function userPermissions()
    {
        $permissions = auth()->user()->getAllPermissions()->pluck('name');
        return response()->json($permissions);
    }

    /**
     * Catálogo completo de permisos existentes en BD.
     *
     * Alimenta la pestaña dinámica "Otros / Sin categorizar" de la UI de
     * permisos: el frontend compara este catálogo contra los permisos
     * curados en constants.js y expone cualquier permiso no cubierto (módulos
     * nuevos o futuros) sin tener que editar el front manualmente. Item #71.
     */
    public function catalog()
    {
        // Reforma de permisos B3: se agrega `contexts` (nombre => panel|portal) para
        // la pantalla de rol a dos columnas. `permissions` (solo nombres) se conserva
        // idéntico para no romper el contrato del catálogo existente.
        $permissions = Permission::orderBy('name')->get(['name', 'context']);
        return response()->json([
            'permissions' => $permissions->pluck('name'),
            'contexts'    => $permissions->pluck('context', 'name'),
        ], 200);
    }

    public function get($role_id)
    {
        $role = Role::find($role_id);

        $permissions = $role->permissions()->pluck('name')->toArray();

        return response()->json(['permissions' => $permissions], 200);
    }

    /**
     * Reforma de permisos B1.3 — RETIRADO. El candado de permisos individuales
     * por usuario se eliminó: el rol es la única fuente de verdad y la asignación
     * vive en la pantalla de Roles. Las rutas get/update-permission-for-user ya
     * no se registran (ver Usuarios/routes.php). Se conservan estos stubs que
     * abortan para evitar re-cableo accidental; borrar en una limpieza futura.
     */
    public function getPermissionUser($userId)
    {
        abort(410, 'Retirado (reforma de permisos B1.3): la asignación de permisos es por rol.');
    }

    public function updatePermissionUser(Request $request, $userId)
    {
        abort(410, 'Retirado (reforma de permisos B1.3): la asignación de permisos es por rol.');
    }



    public function update(Request $request, $role_id)
    {
        $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'required|string|exists:permissions,name',
        ]);

        set_time_limit(0);
        ini_set('memory_limit', '8912M');
        DB::beginTransaction();
        try {
            $role = Role::find($role_id);
            if (!$role) {
                return response()->json(['status' => 404, 'message' => 'Rol no encontrado']);
            }

            $currentPermissions = $role->permissions()->pluck('name')->toArray();
            $newPermissions = $request->input('permissions');

            // Permisos a añadir
            $permissionsToAdd = array_diff($newPermissions, $currentPermissions);

            // Permisos a revocar
            $permissionsToRemove = array_diff($currentPermissions, $newPermissions);

            // Actualizar los permisos del rol
            foreach ($permissionsToAdd as $permission) {
                $role->givePermissionTo($permission);
            }

            foreach ($permissionsToRemove as $permission) {
                $role->revokePermissionTo($permission);
            }

            // Reforma de permisos B1.2: editar un rol toca SOLO role_has_permissions.
            // Se elimina la propagación que antes escribía/borraba los permisos como
            // DIRECTOS en cada usuario del rol (re-sembraba directos en cada edición).
            // Con el flip activo (getAllPermissions = directos ∪ rol) los usuarios
            // reciben el cambio por su rol automáticamente, sin copia a directos.

            DB::commit();
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            return response()->json(['status' => 200, 'message' => 'Permisos del rol actualizados correctamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 500, 'message' => 'Error al actualizar los permisos', 'error' => $e->getMessage()]);
        }
    }

    public function hasPermissionToView($view)
    {
        $view_permission = config('view_permission');
        $permissions = $this->getPermissionForUserAuthenticated();

        $has_permission = collect($view_permission)
            ->filter(function ($value, $key) use ($permissions, $view) {
                $has_permission = false;
                foreach ($value as $v) {
                    if ($view === $v) $has_permission = true;
                }
                return isset($permissions[$key]) && $has_permission;
            });
        if (count($has_permission) || $this->userAutenticated()->isAdmin()) return [
            'data' => true
        ];
        return [
            'data' => false
        ];
    }

    public function allViewHasPermission()
    {
        // Obtener todas las rutas de permisos configuradas
        $view_permission = config('route_permission');

        // Si el usuario es administrador, devuelve todos los permisos
        if ($this->userAutenticated()->isAdmin()) {
            return collect(['super-administrator' => 'super-administrator']);
        }

        // Obtener los permisos del usuario autenticado
        $permissions = $this->getPermissionForUserAuthenticated();

        // Filtrar las rutas que coincidan con los permisos del usuario
        $allowedPermissions = collect($view_permission)
            ->intersectByKeys($permissions->toArray());

        // Devolver los nombres de los permisos (las claves) en lugar de los valores
        return $allowedPermissions->keys();
    }
}
