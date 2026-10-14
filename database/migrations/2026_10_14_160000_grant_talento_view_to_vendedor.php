<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Pedido directo de Irving (2026-10-14), probando el Portal como
 * demo_vendedor: "dale a Vendedor acceso igual que el técnico" — un
 * vendedor viendo su propia ficha no podía ni abrir /talento/colaborador/
 * {su-propio-id} (redirigía al dashboard), a diferencia de un técnico
 * (TECNICO_PLANTA ya trae talento.view, aunque sea por acumulación
 * histórica de permisos — ver nota de "role bloat" en otras partes del
 * código, no se toca aquí).
 *
 * Mismo patrón EXACTO que la migración hermana
 * 2026_10_13_120000_grant_talento_admin_lite_to_mostrador — SOLO
 * talento.view (gate amplio de middleware). El controller de cada pantalla
 * (TalentoColaboradorController::ficha(), TalentoLiquidacionController,
 * etc.) sigue exigiendo uno-mismo/supervisor directo/permiso de staff
 * específico para cualquier cosa que no sea "ver lo propio" — este permiso
 * por sí solo NO destapa compensación/penalizaciones/préstamos/ver a OTROS
 * colaboradores. Ver config/route_permission.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::where('name', 'Vendedor')->first();
        if ($role) {
            $permission = Permission::firstOrCreate(['name' => 'talento.view', 'guard_name' => 'web']);
            $role->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::where('name', 'Vendedor')->first();
        if ($role) {
            $role->revokePermissionTo('talento.view');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
