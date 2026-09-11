<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Item #9990804 — permiso de bypass de emergencia del middleware global de bloqueo
 * proporcional por documento tipo firma pendiente. Decisión de Irving (q4): feature flag +
 * este permiso, solo para super-administrator + DESARROLLADOR (regla del proyecto vía
 * PermissionSyncService::syncPermissionToBaseRoles). Aditivo/idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'talento.bypass_bloqueo_firma', 'guard_name' => 'web']);

        app(PermissionSyncService::class)->syncPermissionToBaseRoles('talento.bypass_bloqueo_firma');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierte el permiso. down() vacío a propósito.
    }
};
