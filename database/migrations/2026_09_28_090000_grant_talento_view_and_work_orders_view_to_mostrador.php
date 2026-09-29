<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Complementa 2026_09_28_083000_grant_work_orders_manage_to_mostrador: ese
 * dio talento.work_orders.manage, pero sin talento.view ni
 * talento.work_orders.view el Mostrador ni siquiera podía ABRIR /talento ni
 * llegar a /talento/api/ordenes (gateados por esos dos permisos en
 * check_route_permission) — encontrado al probar el flujo completo con
 * Playwright. Los tres juntos son lo mínimo real para "Diana puede crear
 * órdenes/flujo para un técnico" (David, 28-sep-2026).
 *
 * Idempotente. down() a propósito NO revoca (misma razón que la migración
 * anterior).
 */
return new class extends Migration
{
    private const PERMISOS = [
        'talento.view',
        'talento.work_orders.view',
    ];

    public function up(): void
    {
        $role = Role::where('name', 'Mostrador')->where('guard_name', 'web')->first();
        if (! $role) {
            return;
        }

        foreach (self::PERMISOS as $permName) {
            $permiso = Permission::where('name', $permName)->where('guard_name', 'web')->first();
            if ($permiso && ! $role->hasPermissionTo($permiso)) {
                $role->givePermissionTo($permiso);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No-op a propósito.
    }
};
