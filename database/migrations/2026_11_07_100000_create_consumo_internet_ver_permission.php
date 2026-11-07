<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * #1186: /admin/consumo muestra usuario, IP y tráfico de todos los clientes y solo pedía sesión
 * iniciada. Decisión de Irving: permiso propio para repartirlo por rol. Sin sufijo `.view` para
 * que el reparto automático nunca lo dé a los demás roles.
 */
return new class extends Migration
{
    private const PERMISO = 'consumo_internet.ver';

    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => self::PERMISO, 'guard_name' => 'web'],
            ['description' => 'Ver el consumo de internet de los clientes (usuario, IP y tráfico en RADIUS)']
        );
        app(PermissionSyncService::class)->syncPermissionToBaseRoles(self::PERMISO);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISO)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
