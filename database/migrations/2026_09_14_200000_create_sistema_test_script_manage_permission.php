<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Item roadmap #9991145 (Fase 1, sub-item de seguimiento de #9991116). TestScriptController es
 * utilería de debug (dd(), scripts de reparación manual de MikroTik/Radius) expuesta hoy solo con
 * 'auth', sin gate de permiso — cubre 5 rutas CRÍTICAS: /script, /information-dev,
 * /data-client-mikrotik/{id}, /data-client-mikrotik-by-ip, /libera-ip/{id}. syncPermissionToBaseRoles
 * asigna SOLO a super-administrator+DESARROLLADOR (no es permiso *_view, no dispara
 * giveToAllOtherRoles). Mismo patrón que
 * app/Modules/Addons/Talento/migrations/2026_09_11_220000_create_talento_documentos_propios_permissions.php.
 */
return new class extends Migration
{
    private const PERMISO = 'sistema.test_script.manage';

    public function up(): void
    {
        Permission::firstOrCreate(['name' => self::PERMISO, 'guard_name' => 'web']);
        app(PermissionSyncService::class)->syncPermissionToBaseRoles(self::PERMISO);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISO)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
