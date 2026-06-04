<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private array $permissions = [
        'facturacion.ver',
        'facturacion.fiscal.editar',
        'facturacion.fiscal.timbrar',
        'facturacion.notif.gestionar',
    ];

    public function up(): void
    {
        $roles = Role::whereIn('name', ['super-administrator', 'DESARROLLADOR'])->get();

        foreach ($this->permissions as $perm) {
            $permission = Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            foreach ($roles as $role) {
                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->permissions as $perm) {
            Permission::where('name', $perm)->delete();
        }
    }
};
