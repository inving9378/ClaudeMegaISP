<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * #988 — Permiso para editar los «Datos de red» de un servicio dedicado. Está declarado en el
 * module.json de Clientes, pero el deploy no corre permissions:sync-roles: sin esta migración no
 * existiría en una instalación nueva y no se le podría asignar a ningún rol. Solo lo reciben
 * super-administrator y DESARROLLADOR.
 */
return new class extends Migration
{
    private const PERMISO = 'clientes.datos_red.editar';

    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => self::PERMISO, 'guard_name' => 'web'],
            ['description' => 'Editar los datos de red de un servicio dedicado (router, interfaz VLAN, bloque e IPs a monitorear)']
        );
        app(PermissionSyncService::class)->syncPermissionToBaseRoles(self::PERMISO);
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISO)->delete();
    }
};
