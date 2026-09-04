<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fase 5c (item roadmap #760). `documentacion-corporativa.bitacora.view` ya
 * existe (Fase 0); esta es la puerta separada para EXPORTAR (CSV), distinta
 * de solo consultar en pantalla — mismo criterio que `documento.download` vs
 * `documento.upload`. Aditiva/idempotente: crea el permiso y lo asigna a los
 * roles base (super-administrator + DESARROLLADOR).
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'documentacion-corporativa.bitacora.export', 'guard_name' => 'web']);

        app(PermissionSyncService::class)->syncPermissionToBaseRoles('documentacion-corporativa.bitacora.export');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierte el permiso. down() vacío a propósito.
    }
};
