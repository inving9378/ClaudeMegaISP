<?php

namespace Database\Seeders\Core;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permiso del canal de auto-actualización PULL (instancias consumidoras).
 *
 * `updates.apply` controla el botón "Actualizar" del banner (UpdateController::apply).
 * NO pertenece a ningún module.json de addon (es un permiso core), por eso se siembra
 * aquí para que se auto-cree en cualquier install fresco. Crítico para instancias
 * consumidoras del SaaS: sin este permiso el banner nunca habilita el botón.
 *
 * Idempotente (firstOrCreate + givePermissionTo no duplica).
 */
class UpdatesPermissionsSeeder extends Seeder
{
    private const FULL_ACCESS_ROLES = ['super-administrator', 'DESARROLLADOR'];

    private array $permissions = [
        'updates.apply' => 'Aplicar actualizaciones de la instancia (canal PULL/banner)',
    ];

    public function run(): void
    {
        foreach ($this->permissions as $name => $label) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $label]
            );
        }

        foreach (self::FULL_ACCESS_ROLES as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo(array_keys($this->permissions));
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('✅ Updates: permiso updates.apply creado y asignado a super-administrator + DESARROLLADOR');
    }
}
