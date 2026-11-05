<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/** Permiso para aprobar un precio distinto al de lista (item #559). El reparto a roles lo decide Irving. */
return new class extends Migration
{
    public function up(): void
    {
        $name = 'crm-empresarial.precios.aprobar';
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        app(PermissionSyncService::class)->syncPermissionToBaseRoles($name);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only.
    }
};
