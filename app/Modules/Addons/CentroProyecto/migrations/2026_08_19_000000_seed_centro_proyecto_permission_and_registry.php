<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * F1 (item #810) — scaffolding del addon Centro de Proyecto: permiso base
 * `centro-proyecto.view` + alta en module_registry + entrada de sidebar.
 *
 * Alcance de `.view` acotado a super-administrator + DESARROLLADOR (patrón de
 * addon-empresa): hoy el módulo solo tiene una página "en construcción", sin
 * datos reales todavía. Ampliar a más roles es decisión de negocio para cuando
 * los paneles (F2-F5, sub-items de #810) tengan contenido real que mostrar.
 */
return new class extends Migration
{
    private string $permission = 'centro-proyecto.view';

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $perm = Permission::firstOrCreate(['name' => $this->permission, 'guard_name' => 'web']);

        foreach (['super-administrator', 'DESARROLLADOR'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && ! $role->hasPermissionTo($perm)) {
                $role->givePermissionTo($perm);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        DB::table('module_sidebar_config')->updateOrInsert(
            ['module_key' => 'centro-proyecto'],
            [
                'sidebar_label'    => 'Centro de Proyecto',
                'is_core'          => false,
                'show_in_sidebar'  => true,
                'sidebar_location' => 'direct',
                'sidebar_section'  => 'modulos',
                'sidebar_position' => 96,
                'sidebar_icon'     => 'activity',
                'sidebar_url'      => '/centro-proyecto',
                'config_moved'     => false,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]
        );

        DB::table('module_registry')->updateOrInsert(
            ['slug' => 'addon-centro-proyecto'],
            [
                'name'              => 'Centro de Proyecto',
                'installed_version' => '0.1.0',
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

        Permission::where('name', $this->permission)->where('guard_name', 'web')->delete();

        DB::table('module_sidebar_config')->where('module_key', 'centro-proyecto')->delete();
        DB::table('module_registry')->where('slug', 'addon-centro-proyecto')->delete();
    }
};
