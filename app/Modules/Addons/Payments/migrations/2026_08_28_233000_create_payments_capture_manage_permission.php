<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Item roadmap #285 — `payments_capture_manage` está declarado en el module.json
 * de Payments (y consumido por ManualPaymentController, captura-pago.blade.php y
 * el sidebar) pero nunca se materializó en la tabla `permissions`. Un permiso
 * ausente no protege nada: `can('payments_capture_manage')` da false para
 * TODOS, incluidos super-administrator/DESARROLLADOR — la pantalla de captura de
 * pago reportado (Paso 2, mostrador) quedó inaccesible desde que se escribió.
 *
 * Se crea el permiso y se asigna a los mismos roles que el resto de la
 * documentación del módulo ya da por hechos (super-administrator + DESARROLLADOR
 * + Mostrador, rol real de Diana/Ariana — mismo patrón que
 * grant_conciliacion_manage_to_mostrador.php). Idempotente y aditiva
 * (givePermissionTo, nunca syncPermissions).
 */
return new class extends Migration
{
    private const PERMISSION = 'payments_capture_manage';
    private const ROLES = ['super-administrator', 'DESARROLLADOR', 'Mostrador'];

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
