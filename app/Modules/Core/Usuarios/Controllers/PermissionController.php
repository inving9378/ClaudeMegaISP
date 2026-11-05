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
use Illuminate\Support\Facades\Schema;
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
        $permissions = auth()->user()?->getAllPermissions()->pluck('name');
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
        // Item #860: se agrega `descriptions` (nombre => description) con el mismo
        // patrón, aditivo — no cambia el significado de las llaves ya consumidas.
        $permissions = Permission::orderBy('name')->get(['name', 'context', 'description']);
        return response()->json([
            'permissions'  => $permissions->pluck('name'),
            'contexts'     => $permissions->pluck('context', 'name'),
            'descriptions' => $permissions->pluck('description', 'name'),
            // Item #866 (Fase C) — nombre => criterio_propios para los permisos que
            // declaran alcance en `permission_scopes` (catálogo de #851 Fase A). El
            // front usa esto para saber junto a qué checkbox mostrar el selector
            // Propios/Todos; permisos ausentes aquí no lo muestran.
            'scopeable'    => Schema::hasTable('permission_scopes')
                ? DB::table('permission_scopes')
                    ->join('permissions', 'permissions.id', '=', 'permission_scopes.permission_id')
                    ->pluck('permission_scopes.criterio_propios', 'permissions.name')
                : (object) [],
        ], 200);
    }

    public function get($role_id)
    {
        $role = Role::find($role_id);

        $permissions = $role->permissions()->pluck('name')->toArray();

        return response()->json([
            'permissions' => $permissions,
            // Item #866 (Fase C) — nombre => 'propios' solo para los permisos de este
            // rol con fila explícita en role_permission_scopes (#865 Fase B). Ausente
            // = 'todos' (default de la tabla), el front lo asume así.
            'scopes' => Schema::hasTable('role_permission_scopes')
                ? DB::table('role_permission_scopes')
                    ->join('permissions', 'permissions.id', '=', 'role_permission_scopes.permission_id')
                    ->where('role_permission_scopes.role_id', $role_id)
                    ->where('role_permission_scopes.scope', 'propios')
                    ->pluck('role_permission_scopes.scope', 'permissions.name')
                : (object) [],
        ], 200);
    }

    /**
     * Guardar los permisos de un rol desde la ventana de Administración → Roles.
     *
     * 2026-11-02: antes se quitaba todo permiso que no viniera marcado, también los de pestañas
     * que la ventana no llegó a mostrar. La pestaña «Otros / Sin categorizar» (Talento y todo
     * módulo nuevo) se carga aparte y, si falla, no aparece ni avisa: guardar así le quitaba
     * al rol esos permisos de golpe y sin rastro (el rol Mostrador perdió sus 17 de Talento la
     * noche del 28 al 29-oct). Ahora la ventana manda además:
     *   - `shown`:  los permisos que mostró. Solo se quita lo que se mostró y quedó desmarcado.
     *   - `loaded`: los que tenía el rol al abrirla. Si el rol cambió mientras tanto, no se
     *               guarda nada (409) y hay que volver a abrirla.
     * Cada guardado con cambios queda en activity_log (log_name `permisos`) con quién y qué.
     */
    public function update(Request $request, $role_id)
    {
        $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'required|string|exists:permissions,name',
            'shown' => 'present|array',
            'shown.*' => 'string',
            'loaded' => 'present|array',
            'loaded.*' => 'string',
            'scopes' => 'array',
            'scopes.*' => 'in:propios,todos',
        ], [
            'shown.present' => 'Vuelve a cargar la página antes de guardar: la ventana de permisos se actualizó.',
            'loaded.present' => 'Vuelve a cargar la página antes de guardar: la ventana de permisos se actualizó.',
        ]);

        $role = Role::find($role_id);
        if (!$role) {
            return response()->json(['status' => 404, 'message' => 'Rol no encontrado']);
        }

        set_time_limit(0);
        ini_set('memory_limit', '8912M');
        DB::beginTransaction();
        try {
            $currentPermissions = $role->permissions()->pluck('name')->toArray();
            $newPermissions = $request->input('permissions', []);
            $shownPermissions = $request->input('shown', []);
            $loadedPermissions = $request->input('loaded', []);

            if (array_diff($currentPermissions, $loadedPermissions) || array_diff($loadedPermissions, $currentPermissions)) {
                DB::rollBack();
                return response()->json([
                    'status' => 409,
                    'message' => 'Los permisos de este rol cambiaron desde que abriste la ventana. Ciérrala y vuelve a abrirla para ver los actuales; no se guardó nada.',
                ]);
            }

            // Permisos a añadir
            $permissionsToAdd = array_values(array_diff($newPermissions, $currentPermissions));

            // Permisos a revocar: solo los que la ventana mostró y quedaron desmarcados
            $permissionsToRemove = array_values(array_intersect(array_diff($currentPermissions, $newPermissions), $shownPermissions));

            // Actualizar los permisos del rol
            foreach ($permissionsToAdd as $permission) {
                $role->givePermissionTo($permission);
            }

            foreach ($permissionsToRemove as $permission) {
                $role->revokePermissionTo($permission);
            }

            $this->syncScopeAssignments($role, $request->input('scopes', []), $shownPermissions);

            if ($permissionsToAdd || $permissionsToRemove) {
                activity('permisos')
                    ->causedBy(Auth::user())
                    ->performedOn($role)
                    ->withProperties(['agregados' => $permissionsToAdd, 'quitados' => $permissionsToRemove])
                    ->log('Permisos del rol ' . $role->name . ': ' . count($permissionsToAdd) . ' agregados, ' . count($permissionsToRemove) . ' quitados');
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

    /**
     * Item #866 (Fase C) — persiste la elección Propios/Todos en role_permission_scopes
     * (#865 Fase B). Solo toca permisos declarados en el catálogo permission_scopes
     * (#851 Fase A); cualquier otra clave del payload se ignora (no confía ciegamente
     * en el front). 'todos' o ausente = borra la fila — es el default de la tabla,
     * así el comportamiento sin fila explícita queda idéntico al actual.
     * Solo toca los permisos que la ventana mostró (`$shown`): uno que no se mostró no
     * pudo elegirse, así que conserva su alcance.
     */
    private function syncScopeAssignments(Role $role, array $scopes, array $shown): void
    {
        if (!Schema::hasTable('permission_scopes') || !Schema::hasTable('role_permission_scopes')) {
            return;
        }

        $scopeablePermissionIds = DB::table('permission_scopes')
            ->join('permissions', 'permissions.id', '=', 'permission_scopes.permission_id')
            ->pluck('permission_scopes.permission_id', 'permissions.name');

        $shown = array_flip($shown);
        foreach ($scopeablePermissionIds as $name => $permissionId) {
            if (!isset($shown[$name])) {
                continue;
            }
            $chosen = $scopes[$name] ?? 'todos';
            $query = DB::table('role_permission_scopes')
                ->where('role_id', $role->id)
                ->where('permission_id', $permissionId);

            if ($chosen === 'propios') {
                if ($query->exists()) {
                    $query->update(['scope' => 'propios', 'updated_at' => now()]);
                } else {
                    DB::table('role_permission_scopes')->insert([
                        'role_id'       => $role->id,
                        'permission_id' => $permissionId,
                        'scope'         => 'propios',
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }
            } else {
                $query->delete();
            }
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
