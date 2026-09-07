<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Item roadmap #9990450. Catálogo de artículos de vendedor (solo lectura, catálogo global,
 * reusa inventory_item_stocks/inventory_items de Vendedores/Inventario, sin tabla propia).
 * Permiso PROPIO y nuevo, distinto de los legacy `selleritems_*` de Vendedores — gatea la
 * vista nueva de Talento sin tocar el gating existente. Nombre alineado a la convención real
 * del módulo (sufijo ".view", igual que talento.custody.view/talento.ventas.view) en vez del
 * ".ver" literal de la propuesta original, para que PermissionSyncService::isViewPermission()
 * lo reconozca. Aditiva/idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'talento.selleritems.view', 'guard_name' => 'web']);

        app(PermissionSyncService::class)->syncPermissionToBaseRoles('talento.selleritems.view');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierte el permiso. down() vacío a propósito.
    }
};
