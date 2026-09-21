<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fase D del plan Vendedores→Talento (ethereal-fluttering-simon.md): comisiones
 * replicadas dentro de Talento, sobre el MISMO motor que ya usa Vendedores
 * (CalculateBalanceSellerService, PaymentByRule, PaymentByRuleDetails,
 * Discount, DiscountSale, commissions_rules_sellers) — sin motor nuevo.
 *
 * Roles: espejo EXACTO de los roles reales que ya tienen acceso a este mismo
 * dinero del lado de Vendedores (verificado en BD de dev, no una suposición):
 * `.view` = seller_view_all_payments_for_seller/seller_follow_payment_client
 * (Mostrador/Vendedor/TECNICO/Almacen/Socio, además de
 * super-administrator/DESARROLLADOR que llegan solos vía syncPermissionToBaseRoles);
 * `.manage` = seller_add_payment (solo super-administrator/DESARROLLADOR, ya
 * cubierto por el sync — sin grants extra).
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'talento.comisiones.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'talento.comisiones.manage', 'guard_name' => 'web']);

        $sync = app(PermissionSyncService::class);
        $sync->syncPermissionToBaseRoles('talento.comisiones.view');
        $sync->syncPermissionToBaseRoles('talento.comisiones.manage');

        $viewPerm = Permission::where('name', 'talento.comisiones.view')->first();
        foreach (['Super Administrador', 'Mostrador', 'Vendedor', 'TECNICO', 'Almacen', 'Socio'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && $viewPerm && !$role->hasPermissionTo($viewPerm)) {
                $role->givePermissionTo($viewPerm);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierte el permiso. down() vacío a propósito.
    }
};
