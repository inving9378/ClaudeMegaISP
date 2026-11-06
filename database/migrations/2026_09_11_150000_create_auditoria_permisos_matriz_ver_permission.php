<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Item #9990765 (Fase 4c de #9990745, sub-item de #9990721) — permiso de la vista de solo
 * lectura "Matriz de permisos por rol" (asignados vs. efectivos). Decisión de Irving (q3,
 * opción 1): permiso nuevo, sincronizado vía PermissionSyncService, asignado a las mismas
 * bases que el resto de las vistas de Auditoría (super-administrator + DESARROLLADOR).
 */
return new class extends Migration
{
    private const PERMISSION = 'auditoria.permisos.ver';

    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => self::PERMISSION, 'guard_name' => 'web'],
            ['description' => 'Ver la matriz de permisos por rol (asignados vs. efectivos) en Auditoría']
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
