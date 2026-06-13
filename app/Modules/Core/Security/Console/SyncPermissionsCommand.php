<?php

namespace App\Modules\Core\Security\Console;

use App\Modules\Core\Security\Services\PermissionSyncService;
use Database\Seeders\RolePermissionRevocationSeeder;
use Illuminate\Console\Command;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'permissions:sync-roles
                            {--manifests : También crear permisos faltantes desde module.json}';

    protected $description = 'Sincroniza permisos faltantes a los roles base (super-administrator, DESARROLLADOR y .view a todos)';

    public function handle(PermissionSyncService $sync): int
    {
        if ($this->option('manifests')) {
            $this->info('Leyendo module.json de todos los addons…');
            $result = $sync->syncFromModuleManifests();
            $this->line('  Permisos nuevos creados: ' . count($result['created']));
            if ($result['created']) {
                foreach ($result['created'] as $p) {
                    $this->line('    + ' . $p);
                }
            }
            $this->line('  Permisos sincronizados: ' . $result['synced']);
            $this->newLine();
        }

        $this->info('Sincronizando permisos faltantes a roles base…');
        $report = $sync->syncAllPermissionsToBaseRoles();

        $this->table(
            ['Rol / Categoría', 'Permisos asignados'],
            [
                ['super-administrator',            $report['super-administrator']],
                ['DESARROLLADOR',                  $report['DESARROLLADOR']],
                ['Otros roles (solo .view)',        $report['others_view']],
                ['TOTAL nuevas asignaciones',       array_sum($report)],
            ]
        );

        if (array_sum($report) === 0) {
            $this->info('✓ Todo ya estaba sincronizado. No se requirieron cambios.');
        } else {
            $this->info('✓ Sincronización completada.');
        }

        // Re-aplicar la matriz de revocación para que el sync no deshaga
        // los permisos que los roles no deben tener según política de negocio.
        $this->newLine();
        $this->info('Re-aplicando matriz de revocación de permisos por rol…');
        $seeder = new RolePermissionRevocationSeeder();
        $seeder->setCommand($this);
        $seeder->run();

        return self::SUCCESS;
    }
}
