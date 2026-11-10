<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Tablero de llamadas de VoIP (/voip): historial, rendimiento por trabajador y
 * grabaciones. Decisión de Irving (2026-11-10): solo administradores.
 *
 * Aditiva: givePermissionTo sobre los roles que ya existan; nunca sync.
 */
return new class extends Migration
{
    private array $permisos = ['voip.dashboard.view', 'voip.grabaciones.escuchar'];

    private array $roles = ['super-administrator', 'DESARROLLADOR', 'Administrador'];

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permisos as $nombre) {
            $perm = Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);

            foreach ($this->roles as $roleName) {
                $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
                if ($role && ! $role->hasPermissionTo($perm)) {
                    $role->givePermissionTo($perm);
                }
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::whereIn('name', $this->permisos)->delete();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
