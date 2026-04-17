<?php

namespace App\Http\Controllers\Module\Administration\Permission;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{

    public function __construct() {}

    public function userPermissions()
    {
        $permissions = auth()->user()->getAllPermissions()->pluck('name');
        return response()->json($permissions);
    }

    public function get($role_id)
    {
        $role = Role::find($role_id);

        $permissions = $role->permissions()->pluck('name')->toArray();

        return response()->json(['permissions' => $permissions], 200);
    }

    public function getPermissionUser($userId)
    {
        $user = User::find($userId);
        $permissions = $user->permissions;
        $permissions = $permissions->pluck('name')->toArray();
        return response()->json(['permissions' => $permissions], 200);
    }


    public function updatePermissionUser(Request $request, $userId)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'required|string|exists:permissions,name',
        ]);

        $user = User::find($userId);
        $roles = $user->roles;

        // Obtener los permisos de cada rol y los permisos directos del usuario
        $permissions = $roles->flatMap(function ($role) {
            return $role->permissions;
        })->merge($user->permissions) // Combina con permisos directos del usuario
            ->unique('id'); // Eliminar duplicados basados en su id

        // Extraer solo los nombres de los permisos en un array
        $currentPermissions = $permissions->pluck('name')->toArray();

        $newPermissions = $request->input('permissions');

        // Permisos a añadir
        $permissionsToAdd = array_diff($newPermissions, $currentPermissions);

        // Permisos a revocar
        $permissionsToRemove = array_diff($currentPermissions, $newPermissions);

        // Añadir nuevos permisos
        foreach ($permissionsToAdd as $permission) {
            $user->givePermissionTo($permission);
        }

        // Revocar permisos eliminados
        foreach ($permissionsToRemove as $permission) {
            $user->revokePermissionTo($permission);
        }

        return response()->json(['status' => 200, 'message' => 'Permisos actualizados correctamente']);
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

            $users = User::role($role->name)->get();

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

            // Actualizar los permisos de los usuarios del rol
            foreach ($users as $user) {
                // Obtener todos los roles del usuario
                $userRoles = $user->roles;

                // Verificar si el permiso a revocar está asociado a otros roles del usuario
                foreach ($permissionsToRemove as $permission) {
                    $hasPermissionThroughOtherRoles = false;

                    foreach ($userRoles as $userRole) {
                        if ($userRole->id !== $role->id && $userRole->hasPermissionTo($permission)) {
                            $hasPermissionThroughOtherRoles = true;
                            break;
                        }
                    }

                    // Solo revocar el permiso si no está asociado a otros roles
                    if (!$hasPermissionThroughOtherRoles) {
                        $user->revokePermissionTo($permission);
                    }
                }

                // Añadir permisos al usuario
                foreach ($permissionsToAdd as $permission) {
                    $user->givePermissionTo($permission);
                }
            }

            DB::commit();
            return response()->json(['status' => 200, 'message' => 'Permisos del rol y de los usuarios actualizados correctamente']);
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
