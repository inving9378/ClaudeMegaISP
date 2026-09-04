<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Item roadmap #923. Permiso propio para administrar el catálogo de puestos, mismo patrón que
 * 'talento.expediente.paquetes.manage' (migración 2026_09_03_100100): NO termina en '.view' a
 * propósito, para que el sync no lo reparta a roles distintos de los base. Aditiva/idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'talento.puestos.manage', 'guard_name' => 'web']);

        app(PermissionSyncService::class)->syncPermissionToBaseRoles('talento.puestos.manage');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierte el permiso. down() vacío a propósito.
    }
};
