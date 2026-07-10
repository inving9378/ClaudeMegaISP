<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permiso de DECISIÓN humana del Circuito (bandeja de la Torre, #313).
 * Capacidad separada de circuito.pause y de roadmap_view: es lo que desbloquea el
 * circuito autónomo, por eso su propio permiso. Aditivo/idempotente (firstOrCreate +
 * givePermissionTo, nunca sync*). Portable a prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        $perm = Permission::firstOrCreate(['name' => 'circuito.decidir', 'guard_name' => 'web']);

        foreach (['super-administrator', 'DESARROLLADOR'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role && ! $role->hasPermissionTo($perm)) {
                $role->givePermissionTo($perm); // aditivo
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $perm = Permission::where('name', 'circuito.decidir')->where('guard_name', 'web')->first();
        if ($perm) {
            $perm->delete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
