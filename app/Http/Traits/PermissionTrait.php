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

        // Combinar ambos conjuntos de permisos y eliminar duplicados por ID
        $allPermissions = $user->permissions;

        // Opcionalmente, puedes pluckear el id y el nombre de los permisos
        return $allPermissions->pluck('id', 'name');
    }
}
