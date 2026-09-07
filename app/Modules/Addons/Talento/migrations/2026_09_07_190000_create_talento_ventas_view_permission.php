<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Item roadmap #9990449. Estadísticas de ventas del colaborador vendedor (solo lectura,
 * self-scoped, reusa StaticsController). Permiso PROPIO y nuevo, distinto de los de
 * Vendedores (seller_view_statics) — gatea la vista/API nueva de Talento sin tocar el
 * gating existente del dashboard de Vendedores. Aditiva/idempotente, mismo patrón que el
 * resto de permisos ".view" de Talento (asignado a roles base vía PermissionSyncService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'talento.ventas.view', 'guard_name' => 'web']);

        app(PermissionSyncService::class)->syncPermissionToBaseRoles('talento.ventas.view');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierte el permiso. down() vacío a propósito.
    }
};
