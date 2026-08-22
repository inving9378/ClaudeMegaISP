<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Item #999 (IPv6 1.7a) — permiso de la pantalla standalone de configuración
 * IPv6 (routers registrados, detección de versión, mapeo de zonas, vista
 * previa del plan de direccionamiento). La ruta ya está gateada por rol
 * (`role:super-administrator|DESARROLLADOR`, precedente exacto de
 * `app/Modules/Addons/Payments/routes.php`), este permiso es la segunda capa
 * usada en el `@can` del blade (mismo patrón que `metodos-pago.blade.php` con
 * `payments_manage_providers`). Nombre sin sufijo `.view` a propósito para que
 * `PermissionSyncService::isViewPermission()` NO lo reparta automáticamente a
 * los demás roles base — se queda solo en super-administrator + DESARROLLADOR.
 *
 * ADITIVA: `firstOrCreate` + `syncPermissionToBaseRoles` (mismo camino que
 * `ModuleLifecycleService::registerPermissions`).
 */
return new class extends Migration
{
    private const NOMBRE = 'ipv6.manage';

    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => self::NOMBRE, 'guard_name' => 'web'],
            ['description' => 'Configurar IPv6 (detección de versión, mapeo de zonas, vista previa de plan de direccionamiento)']
        );

        app(PermissionSyncService::class)->syncPermissionToBaseRoles(self::NOMBRE);
    }

    public function down(): void
    {
        Permission::where('name', self::NOMBRE)->delete();
    }
};
