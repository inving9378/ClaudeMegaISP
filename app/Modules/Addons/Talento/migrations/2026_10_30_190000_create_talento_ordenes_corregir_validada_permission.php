<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * UNI-T D7.1 (#1095, decisión de Irving q1 opción 1): permiso propio y granular para corregir una orden ya
 * validada. Solo roles base (super-administrator + DESARROLLADOR); ampliar a otros roles es decisión posterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'talento.ordenes.corregir_validada', 'guard_name' => 'web']);

        app(PermissionSyncService::class)->syncPermissionToBaseRoles('talento.ordenes.corregir_validada');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierte el permiso.
    }
};
