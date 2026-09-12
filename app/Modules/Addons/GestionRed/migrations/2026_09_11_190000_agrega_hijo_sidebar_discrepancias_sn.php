<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Item roadmap #9990850 (Fase 4b de #9990837) — mismo patrón que la migración
 * `2026_08_29_000000_agrega_hijo_sidebar_mikrotik_sync`: `addon-gestion-red`
 * está en `$sidebarHardcoded` (sidebar.blade.php), así que el `menu[]` del
 * module.json NO se refleja solo. El sidebar real de "Gestión de red" es el
 * blade estático `module-sidebar/gestion-red.blade.php`, que sí pinta hijos
 * dinámicos vía `module_sidebar_config` (sidebar_location='sub_item' +
 * sidebar_parent='gestion-red'). Esta fila es lo que hace que el link
 * "Discrepancias SN" aparezca de verdad, gateado por el permiso ya creado en
 * la Fase 4a.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('module_sidebar_config')->updateOrInsert(
            ['module_key' => 'gestion-red-discrepancias-sn'],
            [
                'show_in_sidebar' => true,
                'sidebar_location' => 'sub_item',
                'sidebar_parent' => 'gestion-red',
                'permission' => 'red.discrepancias.ver',
                'sidebar_section' => 'menu',
                'sidebar_position' => 31,
                'sidebar_icon' => 'fa fa-fw fa-exclamation-triangle',
                'sidebar_label' => 'Discrepancias SN',
                'sidebar_url' => '/red/discrepancias-sn',
                'is_core' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('module_sidebar_config')->where('module_key', 'gestion-red-discrepancias-sn')->delete();
    }
};
