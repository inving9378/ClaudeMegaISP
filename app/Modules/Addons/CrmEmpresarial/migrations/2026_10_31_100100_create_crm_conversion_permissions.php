<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/** Permiso de conversión CRM-E (item #563). El reparto a roles lo decide Irving. */
return new class extends Migration
{
    public function up(): void
    {
        $name = 'crm-empresarial.conversion.manage';
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        app(PermissionSyncService::class)->syncPermissionToBaseRoles($name);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only.
    }
};
