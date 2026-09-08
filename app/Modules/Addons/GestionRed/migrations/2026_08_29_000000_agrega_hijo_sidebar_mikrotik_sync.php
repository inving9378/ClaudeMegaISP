<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Item roadmap #677 — la entrada "Sync Mikrotik" en el `menu[]` del module.json
 * NO se refleja en el sidebar real: `addon-gestion-red` está en
 * `$sidebarHardcoded` (sidebar.blade.php), así que el bloque dinámico de
 * ModuleRegistry::getMenu() lo excluye a propósito. El sidebar de "Gestión de
 * red" es el blade estático `module-sidebar/gestion-red.blade.php`, que sí
 * soporta hijos dinámicos vía `module_sidebar_config` (sidebar_location=
 * 'sub_item' + sidebar_parent='gestion-red', ver SidebarComposer). Esta fila
 * es lo que hace que el link aparezca de verdad.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('module_sidebar_config')->updateOrInsert(
            ['module_key' => 'gestion-red-mikrotik-sync'],
            [
                'show_in_sidebar' => true,
                'sidebar_location' => 'sub_item',
                'sidebar_parent' => 'gestion-red',
                'sidebar_section' => 'menu',
                'sidebar_position' => 30,
                'sidebar_icon' => 'fa fa-fw fa-sync',
                'sidebar_label' => 'Sync Mikrotik',
                'sidebar_url' => '/red/mikrotik-sync',
                'is_core' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('module_sidebar_config')->where('module_key', 'gestion-red-mikrotik-sync')->delete();
    }
};
