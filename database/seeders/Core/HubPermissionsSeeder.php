<?php

namespace Database\Seeders\Core;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class HubPermissionsSeeder extends Seeder
{
    private array $permissions = [
        'view-integrations'       => 'Ver integraciones API',
        'manage-integrations'     => 'Crear/editar integraciones API',
        'rotate-integration-keys' => 'Rotar keys de integración',
        'view-integration-logs'   => 'Ver logs de auditoría de integraciones',
        'validate-integrations'   => 'Validar integraciones contra su proveedor',
    ];

    public function run(): void
    {
        foreach ($this->permissions as $name => $label) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $role = Role::where('name', 'DESARROLLADOR')->where('guard_name', 'web')->first();
        if ($role) {
            $role->givePermissionTo(array_keys($this->permissions));
        }

        $this->command->info('✅ Hub: 5 permisos creados y asignados a DESARROLLADOR');
    }
}
