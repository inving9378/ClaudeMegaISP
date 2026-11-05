<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * FR-11 F1 (#1151): permisos propios de la tarjeta «Datos de facturación».
 * Aditiva e idempotente (givePermissionTo, nunca sync). Quien hoy tiene
 * facturacion.ver / facturacion.fiscal.editar (por rol o directo) recibe el nuevo equivalente.
 */
return new class extends Migration
{
    private const VER = 'facturacion.datos.ver';
    private const EDITAR = 'facturacion.datos.editar';

    public function up(): void
    {
        $ver = Permission::firstOrCreate(['name' => self::VER, 'guard_name' => 'web']);
        $editar = Permission::firstOrCreate(['name' => self::EDITAR, 'guard_name' => 'web']);

        // Roles por nombre (nunca por id): ver y editar según lo aprobado por Irving.
        $this->darARoles(['super-administrator', 'DESARROLLADOR', 'Administrador', 'ADMINISTRADOR_COMPLETO', 'Mostrador', 'SUPERVISOR_MOSTRADOR'], $ver);
        $this->darARoles(['super-administrator', 'DESARROLLADOR', 'Administrador', 'ADMINISTRADOR_COMPLETO'], $editar);

        // Conservar acceso: quien ya tenía el permiso viejo (rol o directo) recibe el nuevo.
        foreach ([['facturacion.ver', $ver], ['facturacion.fiscal.editar', $editar]] as [$viejo, $nuevo]) {
            $old = Permission::where('name', $viejo)->where('guard_name', 'web')->first();
            if (! $old) {
                continue;
            }
            foreach (Role::permission($old)->get() as $role) {
                if (! $role->hasPermissionTo($nuevo)) {
                    $role->givePermissionTo($nuevo);
                }
            }
            foreach (\App\Models\User::permission($old)->get() as $user) {
                if (! $user->hasDirectPermission($nuevo)) {
                    $user->givePermissionTo($nuevo);
                }
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', [self::VER, self::EDITAR])->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function darARoles(array $nombres, Permission $permiso): void
    {
        foreach (Role::whereIn('name', $nombres)->get() as $role) {
            if (! $role->hasPermissionTo($permiso)) {
                $role->givePermissionTo($permiso);
            }
        }
    }
};
