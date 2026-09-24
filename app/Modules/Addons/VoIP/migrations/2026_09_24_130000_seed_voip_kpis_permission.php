<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * MegaVoz Fase 5 — permiso del tablero de KPIs.
 */
return new class extends Migration
{
    private string $permission = 'voip.kpis.view';

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $perm = Permission::firstOrCreate(['name' => $this->permission, 'guard_name' => 'web']);

        foreach (['super-administrator', 'DESARROLLADOR'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && ! $role->hasPermissionTo($perm)) {
                $role->givePermissionTo($perm);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::where('name', $this->permission)->delete();
    }
};
