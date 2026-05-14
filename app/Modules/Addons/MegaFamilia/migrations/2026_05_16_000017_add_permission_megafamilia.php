<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Permisos del módulo addon-megafamilia.
     *
     * El spec original pedía asignar a roles "ADMINISTRADOR" y "SOPORTE";
     * los roles reales en DB son `Administrador` y `TECNICO` — se usan
     * esos. Renombrar después si se crea un role específico de soporte.
     */
    public function up(): void
    {
        $permissions = [
            'megafamilia_admin',
            'megafamilia_support',
            'megafamilia_view',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Roles que reciben todos los permisos (visión completa)
        foreach ([
            'DESARROLLADOR',
            'Administrador',
            'super-administrator',
            'Super Administrador',
        ] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($permissions);
            }
        }

        // Roles de soporte / vista
        foreach (['TECNICO'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo(['megafamilia_support', 'megafamilia_view']);
            }
        }

        // Sincroniza el cache de permisos para usuarios afectados.
        $affectedRoles = ['DESARROLLADOR', 'Administrador', 'super-administrator', 'Super Administrador', 'TECNICO'];
        $users = collect();
        foreach ($affectedRoles as $r) {
            $users = $users->merge(User::role($r)->get());
        }
        foreach ($users->unique('id') as $user) {
            $user->load('permissions', 'roles'); // refresca relaciones
        }
    }

    public function down(): void
    {
        foreach (['megafamilia_admin', 'megafamilia_support', 'megafamilia_view'] as $name) {
            $p = Permission::where('name', $name)->where('guard_name', 'web')->first();
            if ($p) $p->delete();
        }
    }
};
