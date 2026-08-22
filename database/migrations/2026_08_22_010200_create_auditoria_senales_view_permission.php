<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Item #1016 — permiso de la vista de listado (solo lectura) de señales minadas
 * de la bitácora. Asignado a super-administrator + DESARROLLADOR (política del
 * servicio central); nadie más lo recibe automáticamente (sección interna nueva).
 */
return new class extends Migration
{
    private const PERMISSION = 'auditoria.senales.view';

    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => self::PERMISSION, 'guard_name' => 'web'],
            ['description' => 'Ver el listado de señales minadas de la bitácora (Auditoría)']
        );

        app(PermissionSyncService::class)->syncPermissionToBaseRoles(self::PERMISSION);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $perm = Permission::where('name', self::PERMISSION)->where('guard_name', 'web')->first();
        if ($perm) {
            $perm->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
