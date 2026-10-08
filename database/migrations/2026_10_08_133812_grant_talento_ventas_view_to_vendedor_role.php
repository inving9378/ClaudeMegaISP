<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * El rol Vendedor nunca tuvo `talento.ventas.view` (solo lo cargaban
 * super-administrator/DESARROLLADOR) — la pantalla self-service
 * "Mis ventas" (/talento/mis-ventas, TalentoVentasController::misVentas)
 * existía y funcionaba, pero ningún vendedor real podía abrirla: `check_
 * route_permission` + el `authorize()` del controller la bloqueaban con
 * 403. Aditivo (givePermissionTo, nunca syncRoles/syncPermissions) — solo
 * suma acceso, no quita nada a nadie.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'talento.ventas.view', 'guard_name' => 'web']
        );

        $role = Role::where('name', 'Vendedor')->first();
        if ($role && ! $role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        $role = Role::where('name', 'Vendedor')->first();
        if ($role && $role->hasPermissionTo('talento.ventas.view')) {
            $role->revokePermissionTo('talento.ventas.view');
        }
    }
};
