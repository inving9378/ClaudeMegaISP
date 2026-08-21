<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * #838 (Parte B del #795) — el manual pasa de estático a editable: agrega los
 * 4 permisos de escritura (create/edit/delete/publish) junto al ya existente
 * empresa_manual_view. Mismo alcance inicial que la Parte A (item #795): solo
 * super-administrator + DESARROLLADOR — ampliar a más roles cuando Dirección
 * tome dueño del contenido es decisión de negocio, fuera de este item.
 */
return new class extends Migration
{
    private array $permissions = [
        'empresa_manual_create',
        'empresa_manual_edit',
        'empresa_manual_delete',
        'empresa_manual_publish',
    ];

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = Role::whereIn('name', ['super-administrator', 'DESARROLLADOR'])->get();

        foreach ($this->permissions as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            foreach ($roles as $role) {
                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::whereIn('name', $this->permissions)->where('guard_name', 'web')->delete();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
