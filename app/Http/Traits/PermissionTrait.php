<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Auth;

trait PermissionTrait
{
    public function getPermissionForUserAuthenticated()
    {
        $user = Auth::user();

        // Si el usuario es administrador, devolver todos los permisos
        if ($user->isAdmin()) {
            return \Spatie\Permission\Models\Permission::pluck('id', 'name');
        }

        // Directos ∪ rol (getAllPermissions dedup por Spatie). El flip solo SUMA
        // acceso: directos ⊆ (directos ∪ rol) → ningún staff pierde acceso.
        // El bypass por nombre de rol (super-administrator/DESARROLLADOR/…) vive
        // aparte: en CheckRoutePermission (línea 59) y en el short-circuit isAdmin()
        // de arriba — ambos intactos, porque esos roles no tienen literalmente
        // todos los permisos y no pueden resolverse por enumeración.
        $allPermissions = $user->getAllPermissions();

        // Indexado por NOMBRE: el middleware lo consume con isset($permissions[$key]).
        return $allPermissions->pluck('id', 'name');
    }
}
