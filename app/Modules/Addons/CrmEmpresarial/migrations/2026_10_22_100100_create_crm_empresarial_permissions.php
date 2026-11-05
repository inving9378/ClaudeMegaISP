<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/** Permisos de CRM Empresarial (item #555). Aditiva; el reparto a roles lo decide Irving. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['crm-empresarial.expediente.view', 'crm-empresarial.expediente.manage'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            app(PermissionSyncService::class)->syncPermissionToBaseRoles($name);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only.
    }
};
