<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fase C del plan Vendedores→Talento (ethereal-fluttering-simon.md): caja diaria
 * de efectivo replicada dentro de Talento, sobre las MISMAS tablas que ya usa
 * `sellers/cuts/*` (cut_boxs, cut_extras_incomes, cuts_observations,
 * cut_suppliers_expenses, cut_installations) — sin motor de dinero nuevo.
 *
 * Nombre "caja-vendedor" a propósito: `talento.caja.*` ya existe y es la caja
 * de HERRAMIENTAS (custodia, bono de salud) — concepto totalmente distinto.
 *
 * Roles: espejo EXACTO de los roles reales que ya tienen acceso a este mismo
 * dinero del lado de Vendedores (permisos `seller_cuts`/`seller_cuts_close_box`,
 * verificado en BD de dev), no una suposición — super-administrator/DESARROLLADOR
 * llegan solo con syncPermissionToBaseRoles(); ADMINISTRADOR_COMPLETO/CONTADOR
 * se agregan explícito a .view (igual que `seller_cuts`). .manage se queda solo
 * en super-administrator/DESARROLLADOR (igual que `seller_cuts_close_box`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'talento.caja-vendedor.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'talento.caja-vendedor.manage', 'guard_name' => 'web']);

        $sync = app(PermissionSyncService::class);
        $sync->syncPermissionToBaseRoles('talento.caja-vendedor.view');
        $sync->syncPermissionToBaseRoles('talento.caja-vendedor.manage');

        $viewPerm = Permission::where('name', 'talento.caja-vendedor.view')->first();
        foreach (['ADMINISTRADOR_COMPLETO', 'CONTADOR'] as $roleName) {
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
