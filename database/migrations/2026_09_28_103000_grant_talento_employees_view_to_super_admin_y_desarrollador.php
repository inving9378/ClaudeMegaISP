<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Completa 2026_09_28_100000: esa migración creó talento.employees.view y
 * se lo dio a Mostrador, pero NO a super-administrator/DESARROLLADOR — el
 * patrón normal del proyecto es que TODO permiso nuevo se los otorgue
 * automáticamente (PermissionSyncService::syncPermissionToBaseRoles()),
 * pero esta migración ad-hoc no pasó por ahí.
 *
 * Efecto real del hueco, encontrado verificando el menú lateral con
 * Playwright: admin/DESARROLLADOR veían el sidebar de Talento COMO SI
 * fueran un colaborador sin roster completo (el link decía "Dashboard" en
 * vez de "Colaboradores", y faltaban Órdenes/Compensación/etc.) — porque
 * CheckRoutePermission SÍ los deja pasar por su propio bypass de rol
 * (isAdmin()/isDevelopment()), pero `auth()->user()->can(...)` DENTRO de
 * un controller/blade es un chequeo Spatie normal, sin ese bypass — recién
 * ahora, tras esta migración, empieza a evaluar true para ellos de verdad.
 *
 * Idempotente. down() a propósito no revoca (mismo criterio que las
 * migraciones hermanas de esta rama).
 */
return new class extends Migration
{
    private const ROLES = ['super-administrator', 'DESARROLLADOR'];

    public function up(): void
    {
        $permiso = Permission::where('name', 'talento.employees.view')->where('guard_name', 'web')->first();
        if (! $permiso) {
            return;
        }

        foreach (self::ROLES as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role && ! $role->hasPermissionTo($permiso)) {
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
