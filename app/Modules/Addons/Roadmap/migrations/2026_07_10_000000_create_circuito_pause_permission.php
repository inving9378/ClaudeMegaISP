<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permiso del kill switch del Circuito (botón Pausar de la Torre de control).
 * Aditivo e idempotente: firstOrCreate del permiso + givePermissionTo a los dos
 * roles admin (nunca sync* — regla de permisos aditivos). Portable a prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        $perm = Permission::firstOrCreate(['name' => 'circuito.pause', 'guard_name' => 'web']);

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
        $perm = Permission::where('name', 'circuito.pause')->where('guard_name', 'web')->first();
        if ($perm) {
            $perm->delete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
