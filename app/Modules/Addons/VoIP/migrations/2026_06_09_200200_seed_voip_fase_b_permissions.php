<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private array $permissions = [
        'voip.extensiones.view'      => 'Ver listado de extensiones VoIP',
        'voip.extensiones.create'    => 'Crear extensiones VoIP',
        'voip.extensiones.edit'      => 'Editar extensiones VoIP',
        'voip.extensiones.delete'    => 'Eliminar extensiones VoIP',
        'voip.extensiones.provision' => 'Provisionar/desprovisionar extensiones en Asterisk',
        'voip.extensiones.test'      => 'Verificar estado de extensión en Asterisk',
        'voip.grupos.view'           => 'Ver grupos de timbrado VoIP',
        'voip.grupos.create'         => 'Crear grupos de timbrado VoIP',
        'voip.grupos.edit'           => 'Editar grupos de timbrado VoIP',
        'voip.grupos.delete'         => 'Eliminar grupos de timbrado VoIP',
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
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (array_keys($this->permissions) as $name) {
            Permission::where('name', $name)->delete();
        }
    }
};
