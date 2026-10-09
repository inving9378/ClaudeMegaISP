<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sesión 2026-10-09 (decisión de Irving) — link visible en el menú lateral para
 * /cliente/nomenclatura-onu. Mismo mecanismo que GestionRed/migrations/
 * 2026_08_29_000000_agrega_hijo_sidebar_mikrotik_sync.php: module-sidebar/
 * clientes.blade.php ya soporta hijos dinámicos vía module_sidebar_config
 * (sidebar_location='sub_item' + sidebar_parent='clientes').
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('module_sidebar_config')->updateOrInsert(
            ['module_key' => 'cliente-nomenclatura-onu'],
            [
                'show_in_sidebar' => true,
                'sidebar_location' => 'sub_item',
                'sidebar_parent' => 'clientes',
                'permission' => 'client_edit_client',
                'sidebar_section' => 'menu',
                'sidebar_position' => 40,
                'sidebar_icon' => 'fa fa-fw fa-tags',
                'sidebar_label' => 'Nomenclatura ONU',
                'sidebar_url' => '/cliente/nomenclatura-onu',
                'is_core' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('module_sidebar_config')->where('module_key', 'cliente-nomenclatura-onu')->delete();
    }
};
