<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permiso del módulo Manual General de la Empresa (addon-empresa) + alta en
 * module_registry (para que ModuleRegistry::compiled() lo trate como activo).
 *
 * empresa_manual_view se concede a super-administrator + DESARROLLADOR por
 * ahora (mismo alcance inicial que addon-manual): el contenido real de hoy es
 * mayormente "Pendiente de redacción", así que no se reparte a todo el staff
 * todavía. Ampliar a más roles cuando Dirección cargue la redacción oficial
 * es una decisión de negocio, no técnica — queda fuera de este item.
 */
return new class extends Migration
{
    private string $permission = 'empresa_manual_view';

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => $this->permission, 'guard_name' => 'web']);

        foreach (['super-administrator', 'DESARROLLADOR'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && ! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        DB::table('module_registry')->updateOrInsert(
            ['slug' => 'addon-empresa'],
            [
                'name'              => 'Manual General de la Empresa',
                'installed_version' => '1.0.0',
                'type'              => 'addon',
                'active'            => true,
                'installed_at'      => now(),
                'updated_at'        => now(),
            ]
        );
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('name', $this->permission)->where('guard_name', 'web')->delete();

        DB::table('module_registry')->where('slug', 'addon-empresa')->delete();
    }
};
