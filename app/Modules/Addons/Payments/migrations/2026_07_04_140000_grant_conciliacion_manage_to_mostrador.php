<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * conciliacion.manage permanente para el rol Mostrador (decisión Irving).
 *
 * El mostrador debe poder conciliar pagos por WhatsApp (/finanzas/conciliacion-cola,
 * /finanzas/conciliacion-config). Esa pantalla se gatea con el middleware Spatie
 * `permission:conciliacion.manage` (= can(), que HONRA roles), así que el grant al
 * ROL basta — no es la trampa de "solo directos" de check_route_permission (OLT).
 *
 * Aditivo/idempotente: givePermissionTo solo si falta. NUNCA syncRoles/syncPermissions.
 * Resuelve rol y permiso por NOMBRE (portable dev↔prod, nunca por id). Guard web.
 * Forward-only: down() vacío (no se revierte un permiso operativo en uso).
 * Hace permanente/reproducible el grant que se aplica en prod (sobrevive restores y
 * el provisioning de otro ISP), mismo patrón que grant_apply_payments_to_megaisp /
 * portal.colaborador.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rol = Role::where('name', 'Mostrador')->where('guard_name', 'web')->first();
        if (! $rol) {
            return; // portable: si el rol no existe en el entorno, no rompe
        }

        $perm = Permission::where('name', 'conciliacion.manage')->where('guard_name', 'web')->first();
        if ($perm && ! $rol->hasPermissionTo($perm)) {
            $rol->givePermissionTo($perm); // ADITIVO
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierte un permiso operativo en uso.
    }
};
