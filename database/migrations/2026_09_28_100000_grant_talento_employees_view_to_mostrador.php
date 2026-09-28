<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * David (28-sep): "admin, desarrollador y mostrador si debe salirle la
 * lista completa" (el listado de /talento) — los supervisores (relación
 * supervisor_id, no un rol) ven en cambio solo a su equipo.
 *
 * Mismo patrón de las migraciones previas de esta rama para Mostrador
 * (2026_09_28_083000 y 2026_09_28_090000). Idempotente, down() no revoca.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = Role::where('name', 'Mostrador')->where('guard_name', 'web')->first();
        $permiso = Permission::where('name', 'talento.employees.view')->where('guard_name', 'web')->first();

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
