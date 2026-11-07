<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Los permisos del catálogo se declararon en module.json sin migración. La migración original de
 * permisos ya corrió donde existe el módulo, así que aquí se crean sin depender de ella.
 */
return new class extends Migration
{
    private const PERMISOS = ['mapa_red_catalogo_view', 'mapa_red_catalogo_manage'];

    public function up(): void
    {
        foreach (self::PERMISOS as $nombre) {
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
            app(PermissionSyncService::class)->syncPermissionToBaseRoles($nombre);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: pueden estar asignados a roles.
    }
};
