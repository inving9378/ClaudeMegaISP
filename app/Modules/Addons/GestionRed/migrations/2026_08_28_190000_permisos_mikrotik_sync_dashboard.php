<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Item roadmap #677 — permisos del dashboard read-only de sync Mikrotik
 * (servicios pending/failed) + el botón manual "Reintentar sync".
 *
 * Guion bajo a propósito (convención jerárquica del CLAUDE.md, item #538):
 * NO termina en `.view`, así que `PermissionSyncService::isViewPermission()`
 * no lo reparte a todos los roles — se queda en super-administrator y
 * DESARROLLADOR (igual que el resto de permisos de GestionRed:
 * router_view_router, olt_view, etc.), aditiva/idempotente.
 */
return new class extends Migration
{
    private const PERMISOS = [
        'mikrotik_sync_view_dashboard' => 'Ver dashboard de sincronización Mikrotik (servicios pendientes/fallidos)',
        'mikrotik_sync_retry' => 'Reintentar manualmente la sincronización Mikrotik de un servicio',
    ];

    public function up(): void
    {
        foreach (self::PERMISOS as $nombre => $descripcion) {
            Permission::firstOrCreate(
                ['name' => $nombre, 'guard_name' => 'web'],
                ['description' => $descripcion]
            );

            app(PermissionSyncService::class)->syncPermissionToBaseRoles($nombre);
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', array_keys(self::PERMISOS))->delete();
    }
};
