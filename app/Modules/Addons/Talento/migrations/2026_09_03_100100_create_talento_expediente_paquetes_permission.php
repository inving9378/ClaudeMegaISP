<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Item roadmap #870 (Expediente RH — Hijo D1). Permiso propio para administrar el paquete de
 * documentos por puesto, igual patrón que el permiso hermano 'talento.expediente.view'
 * (migración 2026_08_26_220200). Aditiva/idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'talento.expediente.paquetes.manage', 'guard_name' => 'web']);

        app(PermissionSyncService::class)->syncPermissionToBaseRoles('talento.expediente.paquetes.manage');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierte el permiso. down() vacío a propósito.
    }
};
