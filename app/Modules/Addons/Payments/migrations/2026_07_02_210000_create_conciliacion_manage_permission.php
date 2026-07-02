<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * FASE 6 (F6.2) — Permiso propio de la cola de conciliación de Tere.
 *
 * conciliacion.manage — autoriza ver la cola y CONFIRMAR/RECHAZAR pagos
 * conciliados. Permiso DEDICADO (no se reusa payments_capture_manage, que es la
 * captura de mostrador de Diana/Ariana — trabajo distinto).
 *
 * Se asigna a super-administrator (rol real de Tere, MARIA TERESA id 6) +
 * DESARROLLADOR. Idempotente y aditiva.
 */
return new class extends Migration
{
    private const PERMISSION = 'conciliacion.manage';
    private const ROLES = ['super-administrator', 'DESARROLLADOR'];

    public function up(): void
    {
        $perm = Permission::firstOrCreate([
            'name'       => self::PERMISSION,
            'guard_name' => 'web',
        ]);

        foreach (self::ROLES as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role && !$role->hasPermissionTo($perm)) {
                $role->givePermissionTo($perm);
            }
        }
    }

    public function down(): void
    {
        $perm = Permission::where('name', self::PERMISSION)->first();
        if ($perm) {
            $perm->delete(); // quita el permiso y sus asignaciones
        }
    }
};
