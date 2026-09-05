<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Item #9990375 — pestaña "Actividad del equipo" de la Torre: quién reclamó/cerró qué item,
 * cuánto tiempo tomó, ejecuciones del circuito y commits, por rango de fechas. Solo lectura.
 *
 * `.view` en el nombre → sin `auto_grant_view_base_roles` (default false), lo reparte
 * `syncPermissionToBaseRoles` SOLO a super-administrator + DESARROLLADOR, tal como pide el item.
 *
 * ADITIVA: `firstOrCreate` + `syncPermissionToBaseRoles`. NUNCA `syncPermissions`.
 */
return new class extends Migration
{
    private const PERMISO = 'torre.actividad.view';

    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => self::PERMISO, 'guard_name' => 'web'],
            ['description' => 'Ver la pestaña "Actividad del equipo" de la Torre (sesiones/terminales, tiempo en tarea, ejecuciones y commits)']
        );

        app(PermissionSyncService::class)->syncPermissionToBaseRoles(self::PERMISO);
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISO)->delete();
    }
};
