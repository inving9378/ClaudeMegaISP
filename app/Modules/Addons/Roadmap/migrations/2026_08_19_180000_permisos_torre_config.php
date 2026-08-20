<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * ENTREGA 1 — permisos del panel de configuración de la Torre.
 *
 *   · `torre.config.view` — ver la política vigente. Se da a TODOS los roles base.
 *   · `torre.config.edit` — guardar cambios. Sólo `super-administrator` + `DESARROLLADOR`.
 *
 * Sin `.edit` el panel se ve COMPLETO pero en solo lectura: los controles se pintan deshabilitados,
 * **no se ocultan**. Que todos vean bajo qué política corre el circuito es parte del valor — un
 * panel que se esconde de quien no puede editarlo deja a media empresa sin saber qué está pasando.
 *
 * ADITIVA: `firstOrCreate` + `syncPermissionToBaseRoles` (el mismo camino que
 * `ModuleLifecycleService::registerPermissions`). NUNCA `syncPermissions`, que borraría lo existente.
 */
return new class extends Migration
{
    private const PERMISOS = [
        ['name' => 'torre.config.view', 'description' => 'Ver la configuración de la Torre de control'],
        ['name' => 'torre.config.edit', 'description' => 'Modificar la configuración de la Torre de control'],
    ];

    public function up(): void
    {
        $sync = app(PermissionSyncService::class);

        foreach (self::PERMISOS as $p) {
            Permission::firstOrCreate(
                ['name' => $p['name'], 'guard_name' => 'web'],
                ['description' => $p['description']]
            );

            // Reparte a los roles base: super-administrator + DESARROLLADOR siempre; los `.view`
            // también al resto. `torre.config.edit` NO lleva `.view` en el nombre, así que se
            // queda sólo en los dos administradores — que es justo lo que se quiere.
            $sync->syncPermissionToBaseRoles($p['name']);
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', array_column(self::PERMISOS, 'name'))->delete();
    }
};
