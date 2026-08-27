<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Item #636 — permiso que gatea el widget flotante IaChatFloat.vue (#9) al montarlo
 * en el layout. El componente existía sin consumidor; Irving aprobó (q2) gatearlo
 * por permiso Spatie asignado por defecto a admin, en vez de abrirlo a todo
 * autenticado (costo real de API por mensaje). Nombre sin sufijo `.view` a propósito
 * para que `PermissionSyncService::isViewPermission()` NO lo reparta automáticamente
 * a los demás roles base — se queda solo en super-administrator + DESARROLLADOR.
 *
 * ADITIVA: `firstOrCreate` + `syncPermissionToBaseRoles` (mismo camino que
 * `ModuleLifecycleService::registerPermissions`).
 */
return new class extends Migration
{
    private const NOMBRE = 'usar-ia-chat';

    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => self::NOMBRE, 'guard_name' => 'web'],
            ['description' => 'Usar el widget flotante de chat IA']
        );

        app(PermissionSyncService::class)->syncPermissionToBaseRoles(self::NOMBRE);
    }

    public function down(): void
    {
        Permission::where('name', self::NOMBRE)->delete();
    }
};
