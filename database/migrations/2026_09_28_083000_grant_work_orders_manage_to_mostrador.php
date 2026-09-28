<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Talento — el rol Mostrador (ej. Diana) puede crear órdenes de trabajo y de
 * flujo de campo para un técnico (David, 28-sep-2026). Hasta ahora
 * `talento.work_orders.manage` solo lo tenían super-administrator y
 * DESARROLLADOR (más la excepción puntual de supervisor directo, resuelta
 * en código vía talento_colaboradores.supervisor_id, sin permiso Spatie).
 *
 * Idempotente: solo otorga si el rol existe y aún no lo tiene. down() a
 * propósito NO revoca — si algún día se decide quitarlo, es una decisión
 * explícita aparte, no un rollback automático de esta migración.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = Role::where('name', 'Mostrador')->where('guard_name', 'web')->first();
        $permiso = Permission::where('name', 'talento.work_orders.manage')->where('guard_name', 'web')->first();

        if ($role && $permiso && ! $role->hasPermissionTo($permiso)) {
            $role->givePermissionTo($permiso);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No-op a propósito.
    }
};
