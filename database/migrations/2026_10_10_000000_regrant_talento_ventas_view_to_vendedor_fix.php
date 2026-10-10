<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Re-otorga `talento.ventas.view` al rol Vendedor — la migración anterior
 * (2026_10_08_133812_grant_talento_ventas_view_to_vendedor_role) quedó marcada
 * "Ran" pero el permiso NUNCA se asignó de verdad: `role_has_permissions` solo
 * tenía super-administrator/DESARROLLADOR (id 1/10), nunca Vendedor (id 6).
 * Hallazgo real 2026-10-10: un vendedor de prueba con rol único Vendedor
 * seguía recibiendo "Sin datos de ventas disponibles." en "Mi progreso" del
 * Portal. Causa exacta sin confirmar (posible caché de permisos stale en el
 * momento de aquella migración) — esta corrige el estado SIN depender de
 * `hasPermissionTo()` antes de otorgar (ese guard pudo ser justo lo que
 * calló el fallo anterior): `givePermissionTo()` de Spatie ya es idempotente
 * por sí solo, no duplica si ya estuviera.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => 'talento.ventas.view', 'guard_name' => 'web']);
        $role = Role::where('name', 'Vendedor')->first();
        if ($role) {
            $role->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No revoca: la intención original (migración de hace 2 días) ya era
        // dar este permiso de forma permanente. Si algún día se decide quitarlo,
        // que sea una migración explícita de revocación, no el down() de un fix.
    }
};
