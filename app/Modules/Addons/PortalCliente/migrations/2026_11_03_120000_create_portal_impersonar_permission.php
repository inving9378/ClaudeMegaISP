<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Item #553 — permiso `portal.impersonar` («Entrar como este cliente»). Aditiva/idempotente:
 * solo super-administrator + DESARROLLADOR (roles base); a Soporte u otros se les da a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'portal.impersonar', 'guard_name' => 'web']);
        app(PermissionSyncService::class)->syncPermissionToBaseRoles('portal.impersonar');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only (puede estar asignado a roles).
    }
};
