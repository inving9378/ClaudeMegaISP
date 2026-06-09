<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private array $permissions = [
        'voip.view'                => 'Ver módulo VoIP',
        'voip.troncales.view'      => 'Ver listado de troncales',
        'voip.troncales.create'    => 'Crear troncales VoIP',
        'voip.troncales.edit'      => 'Editar troncales VoIP',
        'voip.troncales.delete'    => 'Eliminar troncales VoIP',
        'voip.troncales.provision' => 'Provisionar/desprovisionar troncales en Asterisk',
        'voip.troncales.test'      => 'Probar conexión ARI y verificar estado de troncal',
    ];

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permissions as $name => $description) {
            $perm = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

            foreach (['super-administrator', 'DESARROLLADOR'] as $roleName) {
                $role = Role::where('name', $roleName)->first();
                if ($role && ! $role->hasPermissionTo($perm)) {
                    $role->givePermissionTo($perm);
                }
            }

            // .view permisos también van a todos los demás roles
            if (str_ends_with($name, '.view')) {
                foreach (Role::whereNotIn('name', ['super-administrator', 'DESARROLLADOR'])->get() as $role) {
                    if (! $role->hasPermissionTo($perm)) {
                        $role->givePermissionTo($perm);
                    }
                }
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Registro en module_sidebar_config
        DB::table('module_sidebar_config')->updateOrInsert(
            ['module_key' => 'voip'],
            [
                'sidebar_label'    => 'VoIP',
                'is_core'          => false,
                'show_in_sidebar'  => true,
                'sidebar_location' => 'direct',
                'sidebar_section'  => 'modulos',
                'sidebar_position' => 95,
                'sidebar_icon'     => 'phone',
                'sidebar_url'      => '/voip/troncales',
                'config_moved'     => false,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]
        );

        // Registro en module_registry
        DB::table('module_registry')->updateOrInsert(
            ['slug' => 'addon-voip'],
            [
                'name'              => 'VoIP',
                'installed_version' => '1.0.0',
                'type'              => 'addon',
                'active'            => true,
                'installed_at'      => now(),
                'updated_at'        => now(),
            ]
        );
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (array_keys($this->permissions) as $name) {
            Permission::where('name', $name)->delete();
        }

        DB::table('module_sidebar_config')->where('module_key', 'voip')->delete();
        DB::table('module_registry')->where('slug', 'addon-voip')->delete();
    }
};
