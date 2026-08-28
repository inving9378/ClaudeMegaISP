<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fase 4 (item roadmap #666). `documentacion-corporativa.concesion.manage`
 * gatea crear/editar concesiones y sus pagos, distinto del `.apartado.xiii.view`
 * que ya trae el rol `consejo` (Fase 0) — el consejo VE el apartado, no
 * administra sus registros. Aditiva/idempotente, igual que el resto de altas
 * de permiso del módulo: crea el permiso y lo asigna a los roles base
 * (super-administrator + DESARROLLADOR).
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'documentacion-corporativa.concesion.manage', 'guard_name' => 'web']);

        app(PermissionSyncService::class)->syncPermissionToBaseRoles('documentacion-corporativa.concesion.manage');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierte el permiso. down() vacío a propósito.
    }
};
